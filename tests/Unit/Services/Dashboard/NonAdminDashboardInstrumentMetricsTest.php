<?php

namespace Tests\Unit\Services\Dashboard;

use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetEstablishmentResult;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\ComgesResultsService;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Services\Metas\MetasEstablishmentLatestEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class NonAdminDashboardInstrumentMetricsTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    public function test_instrument_widgets_link_directly_to_modules(): void
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

        $comgesType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        $metasType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'metas-18834',
                'nombre_indicador' => 'Metas 18834',
                'estado' => true,
                'orden' => 2,
            ],
        ));

        foreach (['comges', 'metas-18834'] as $moduleKey) {
            ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
                'cen_hie_dependency_id' => 1,
                'module_key' => $moduleKey,
                'enabled' => true,
            ]));
        }

        foreach ([$comgesType, $metasType] as $type) {
            IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
                'indicator_type_id' => $type->id,
                'year_id' => $year->id,
                'cen_hie_dependency_id' => 1,
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => true,
            ]));
        }

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $service = app(NonAdminDashboardService::class);
        $instrumentos = collect($service->forUser($user)['instrumentos'] ?? []);

        $comges = $instrumentos->firstWhere('slug', 'comges');
        $metas = $instrumentos->firstWhere('slug', 'metas-18834');

        $this->assertNotNull($comges);
        $this->assertSame(route('voyager.compromisos.index'), $comges['detail_url']);
        $this->assertSame(route('metas.instrument.index', $metasType), $metas['detail_url']);
        $this->assertSame(route('voyager.compromisos.index'), $service->instrumentModuleUrl($user, 'comges'));
        $this->assertSame(route('metas.instrument.index', $metasType), $service->instrumentModuleUrl($user, 'metas-18834'));
    }

    public function test_metas_instrument_percent_matches_latest_evaluation_average(): void
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

        $metasType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
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
            'indicator_type_id' => $metasType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $centerA = $this->createMinimalCenCenter(['nombre_corto' => 'Centro A']);
        $centerB = $this->createMinimalCenCenter(['nombre_corto' => 'Centro B']);

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $centerA->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $metasType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'resultado_global' => 50.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $centerB->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $metasType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'resultado_global' => 70.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $centerB->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $metasType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'FINAL',
            'resultado_global' => 90.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $expected = app(MetasEstablishmentLatestEvaluationService::class)
            ->averageLatestGlobalPercent($metasType, $year, 1);

        $instrumentos = app(NonAdminDashboardService::class)->forUser($user)['instrumentos'] ?? [];
        $metas = collect($instrumentos)->firstWhere('slug', 'metas-18834');

        $this->assertSame(70.0, $expected);
        $this->assertSame($expected, $metas['percent']);
    }

    public function test_comges_instrument_percent_uses_latest_global_evaluation(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));

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

        $comgesType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'comges',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $comgesType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        ComCommitment::unguarded(fn () => ComCommitment::create([
            'year_id' => $year->id,
            'clave' => 'C1',
            'nombre' => 'Compromiso',
            'orden' => 1,
        ]));

        $commitments = ComCommitment::query()->where('year_id', $year->id)->get();
        $expected = app(ComgesResultsService::class)->latestGlobalCompliancePercent($commitments, $year, 1);

        $instrumentos = app(NonAdminDashboardService::class)->forUser($user)['instrumentos'] ?? [];
        $comges = collect($instrumentos)->firstWhere('slug', 'comges');

        $this->assertSame($expected, $comges['percent']);
    }
}
