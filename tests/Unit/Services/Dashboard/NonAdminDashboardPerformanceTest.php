<?php

namespace Tests\Unit\Services\Dashboard;

use App\Enums\MetaOperator;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\NonAdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class NonAdminDashboardPerformanceTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    /**
     * Fixture: 4 indicadores Metas × 2 centros (sin COMGES).
     * Baseline dev (pre SDD-138): ~159 queries en cold cache.
     * Post SDD-138: umbral ≤90 (reducción ≥40 %).
     * Post enmienda SDD-118 (alertas soporte): +1 query guardia ModuleAccess/config.
     * Post SDD-151 (alertas periodo incompleto): +50 queries MetIndicatorIncompletePeriodService.
     * Post SDD-173 fase 4: batch IndicatorTypeSetting por contexto SS/año (sin exists() por indicador).
     * Eager sensibilidad COMGES (fase 2) no aplica al fixture Metas-only; umbral sin cambio material.
     */
    private const COLD_CACHE_MAX_QUERIES = 141;

    /** Tras warm forUser, detailRows('ambitos') debe usar detail_ambito_indicators del payload. */
    private const DETAIL_AMBITO_WARM_MAX_QUERIES = 5;

    public function test_for_user_cold_cache_query_count_within_threshold(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $indicatorType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'metas-18834',
                'nombre_indicador' => 'Metas 18834',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $centerA = $this->createMinimalCenCenter(['nombre_corto' => 'Centro A']);
        $centerB = $this->createMinimalCenCenter(['nombre_corto' => 'Centro B']);

        foreach ([$centerA, $centerB] as $center) {
            MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
                'cen_center_id' => $center->id,
                'year_id' => $year->id,
                'indicator_type_id' => $indicatorType->id,
                'cen_hie_dependency_id' => 1,
            ]));
        }

        for ($i = 1; $i <= 4; $i++) {
            $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $indicatorType->id,
                'year_id' => $year->id,
                'clave' => 'MT-0'.$i,
                'nombre' => 'Indicador Metas '.$i,
                'meta_valor' => 100,
                'meta_operador' => MetaOperator::MayorIgual,
                'estado' => true,
                'orden' => $i,
            ]));

            foreach ([$centerA, $centerB] as $center) {
                MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                    'met_indicator_id' => $metIndicator->id,
                    'year_id' => $year->id,
                    'cen_hie_dependency_id' => 1,
                    'cen_center_id' => $center->id,
                    'seleccionado' => true,
                    'aplica' => true,
                    'meta_efectiva' => 100,
                    'ponderacion' => 100,
                ]));

                MetResult::unguarded(fn () => MetResult::create([
                    'met_indicator_id' => $metIndicator->id,
                    'cen_center_id' => $center->id,
                    'cen_hie_dependency_id' => 1,
                    'year_id' => $year->id,
                    'periodo' => 'ANUAL',
                    'resultado' => 40 + ($i * 5),
                    'etapa' => 'AUTOEVALUACION',
                ]));
            }
        }

        $this->actingAs($user);
        session(['indicator_year' => $year]);
        Cache::flush();

        DB::enableQueryLog();
        $start = microtime(true);

        $payload = app(NonAdminDashboardService::class)->forUser($user);

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
        $queryCount = count(DB::getQueryLog());

        $this->assertNotNull($payload);
        $this->assertNotEmpty($payload['instrumentos'] ?? []);
        $this->assertLessThanOrEqual(
            self::COLD_CACHE_MAX_QUERIES,
            $queryCount,
            "Cold cache ejecutó {$queryCount} queries (umbral ".self::COLD_CACHE_MAX_QUERIES.')',
        );
        $this->assertLessThan(5000, $elapsedMs, "Cold cache tardó {$elapsedMs} ms (umbral 5000)");
    }

    public function test_for_user_warm_cache_uses_fewer_queries_than_cold(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $this->actingAs($user);
        session(['indicator_year' => $year]);
        Cache::flush();

        $service = app(NonAdminDashboardService::class);

        DB::enableQueryLog();
        $service->forUser($user);
        $coldQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $service->forUser($user);
        $warmQueries = count(DB::getQueryLog());

        $this->assertSame(0, $warmQueries, 'Warm cache no debe ejecutar queries SQL');
        $this->assertGreaterThan(0, $coldQueries);
    }

    public function test_detail_ambito_rows_after_warm_for_user_uses_payload_index(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $indicatorType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'metas-18834',
                'nombre_indicador' => 'Metas 18834',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $center = $this->createMinimalCenCenter(['nombre_corto' => 'Centro A']);

        for ($i = 1; $i <= 2; $i++) {
            $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $indicatorType->id,
                'year_id' => $year->id,
                'clave' => 'MT-0'.$i,
                'nombre' => 'Indicador Metas '.$i,
                'meta_valor' => 100,
                'meta_operador' => MetaOperator::MayorIgual,
                'estado' => true,
                'orden' => $i,
            ]));

            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $metIndicator->id,
                'year_id' => $year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $center->id,
                'seleccionado' => true,
                'aplica' => true,
                'meta_efectiva' => 100,
                'ponderacion' => 100,
            ]));

            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $metIndicator->id,
                'cen_center_id' => $center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $year->id,
                'periodo' => 'ANUAL',
                'resultado' => 40 + ($i * 5),
                'etapa' => 'AUTOEVALUACION',
            ]));
        }

        $this->actingAs($user);
        session(['indicator_year' => $year]);
        Cache::flush();

        $service = app(NonAdminDashboardService::class);
        $payload = $service->forUser($user);

        $this->assertNotEmpty($payload['detail_ambito_indicators'] ?? []);

        DB::enableQueryLog();
        $rows = $service->detailRows($user, 'ambitos', 'sin-ambito');
        $queryCount = count(DB::getQueryLog());

        $this->assertNotEmpty($rows);
        $this->assertLessThanOrEqual(
            self::DETAIL_AMBITO_WARM_MAX_QUERIES,
            $queryCount,
            "detailRows('ambitos') tras warm forUser ejecutó {$queryCount} queries (umbral ".self::DETAIL_AMBITO_WARM_MAX_QUERIES.')',
        );
    }
}
