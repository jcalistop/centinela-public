<?php

namespace Tests\Unit\Services\Dashboard;

use App\Enums\Comges\EvaluacionTipo;
use App\Enums\MetaOperator;
use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\IndicatorAmbito;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\ComgesResultsService;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class NonAdminDashboardAmbitoFiltersTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    public function test_sin_ambito_excludes_indicators_from_disabled_metas_instruments(): void
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

        $enabledType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'metas-18834',
                'nombre_indicador' => 'Metas 18834',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        $disabledType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'metas-19664'],
            [
                'clave_indicador' => 'metas-19664',
                'nombre_indicador' => 'Metas 19664',
                'estado' => true,
                'orden' => 2,
            ],
        ));

        foreach ([$enabledType, $disabledType] as $type) {
            ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
                'cen_hie_dependency_id' => 1,
                'module_key' => $type->route,
                'enabled' => true,
            ]));
        }

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $enabledType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $disabledType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'NO_INICIADO',
        ]));

        $center = $this->createMinimalCenCenter();

        foreach ([$enabledType, $disabledType] as $type) {
            $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $type->id,
                'year_id' => $year->id,
                'clave' => 'MT-'.$type->route,
                'nombre' => 'Indicador '.$type->route,
                'meta_valor' => 100,
                'meta_operador' => MetaOperator::MayorIgual,
                'estado' => true,
                'orden' => 1,
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
                'resultado' => 80,
                'etapa' => 'AUTOEVALUACION',
            ]));
        }

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $widget = collect(app(NonAdminDashboardService::class)->forUser($user)['ambitos'] ?? [])
            ->firstWhere('slug', 'sin-ambito');
        $rows = app(NonAdminDashboardService::class)->detailRows($user, 'ambitos', 'sin-ambito');

        $this->assertNotNull($widget);
        $this->assertSame(1, $widget['indicator_count']);
        $this->assertCount(1, collect($rows)->where('source', 'Metas'));
    }

    public function test_sin_ambito_comges_uses_latest_evaluation_when_final_is_missing(): void
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

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'year_id' => $year->id,
            'indicator_type_id' => $comgesType->id,
            'clave' => 'C1',
            'nombre' => 'Compromiso',
            'orden' => 1,
        ]));

        $comIndicator = ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'COMGES-01',
            'nombre' => 'Indicador COMGES sin ámbito',
            'ponderacion_indicador' => 100,
        ]));

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1, 2, 3]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffLinkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $comIndicator->id,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'promedio_pais' => null,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $cutoffLinkId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_evaluations')->insert([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => 1,
            'tipo_evaluacion' => EvaluacionTipo::Autoevaluacion->value,
            'cumplimiento' => 4,
            'numerador' => 4,
            'denominador' => 1,
            'resultado' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $commitments = ComCommitment::with([
            'comIndicators.comIndicatorCutoffs.cutoff',
            'comIndicators.comIndicatorCutoffs.comActions.comEvaluations' => fn ($q) => $q->where('cen_hie_dependency_id', 1),
        ])->where('year_id', $year->id)->get();

        $indicator = $commitments->flatMap->comIndicators->first();
        $expected = app(ComgesResultsService::class)->latestIndicatorCompliancePercent($indicator, $year, 1);

        $widget = collect(app(NonAdminDashboardService::class)->forUser($user)['ambitos'] ?? [])
            ->firstWhere('slug', 'sin-ambito');

        $this->assertNotNull($expected);
        $this->assertNotNull($widget);
        $this->assertSame($expected, $widget['percent']);
    }

    public function test_empty_catalog_ambitos_are_hidden_from_widget(): void
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

        $ambitos = app(NonAdminDashboardService::class)->forUser($user)['ambitos'] ?? [];

        $this->assertSame([], $ambitos);
    }

    public function test_ambito_widgets_ordered_by_compliance_desc(): void
    {
        $this->artisan('db:seed', ['--class' => 'IndicatorAmbitoSeeder']);

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

        $metType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
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
            'module_key' => $metType->route,
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $metType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $hospitalizacion = IndicatorAmbito::query()->where('slug', 'hospitalizacion')->firstOrFail();
        $ges = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();
        $center = $this->createMinimalCenCenter();
        $assignment = app(IndicatorAmbitoAssignmentService::class);

        foreach (
            [
                ['clave' => 'MT-HOSP', 'ambito' => $hospitalizacion, 'resultado' => 40],
                ['clave' => 'MT-GES', 'ambito' => $ges, 'resultado' => 90],
            ] as $row
        ) {
            $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $metType->id,
                'year_id' => $year->id,
                'clave' => $row['clave'],
                'nombre' => 'Indicador '.$row['clave'],
                'meta_valor' => 100,
                'meta_operador' => MetaOperator::MayorIgual,
                'estado' => true,
                'orden' => 1,
            ]));

            $assignment->sync($metIndicator, [$row['ambito']->id]);

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
                'resultado' => $row['resultado'],
                'etapa' => 'AUTOEVALUACION',
            ]));
        }

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $slugs = array_column(
            app(NonAdminDashboardService::class)->forUser($user)['ambitos'] ?? [],
            'slug',
        );

        $this->assertSame(['ges', 'hospitalizacion'], $slugs);
    }

    public function test_ambito_detail_rows_ordered_by_compliance_desc(): void
    {
        $this->artisan('db:seed', ['--class' => 'IndicatorAmbitoSeeder']);

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

        $metType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
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
            'module_key' => $metType->route,
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $metType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $ges = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();
        $center = $this->createMinimalCenCenter();
        $assignment = app(IndicatorAmbitoAssignmentService::class);

        foreach (
            [
                ['clave' => 'MT-GES-LOW', 'resultado' => 55],
                ['clave' => 'MT-GES-HIGH', 'resultado' => 95],
            ] as $row
        ) {
            $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $metType->id,
                'year_id' => $year->id,
                'clave' => $row['clave'],
                'nombre' => 'Indicador '.$row['clave'],
                'meta_valor' => 100,
                'meta_operador' => MetaOperator::MayorIgual,
                'estado' => true,
                'orden' => 1,
            ]));

            $assignment->sync($metIndicator, [$ges->id]);

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
                'resultado' => $row['resultado'],
                'etapa' => 'AUTOEVALUACION',
            ]));
        }

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $rows = app(NonAdminDashboardService::class)->detailRows($user, 'ambitos', 'ges');
        $indicatorRows = array_values(array_filter($rows, fn (array $row): bool => empty($row['is_summary'])));
        $summary = collect($rows)->firstWhere('is_summary', true);

        $this->assertSame('[MT-GES-HIGH] Indicador MT-GES-HIGH', $indicatorRows[0]['label']);
        $this->assertSame('[MT-GES-LOW] Indicador MT-GES-LOW', $indicatorRows[1]['label']);
        $this->assertNotNull($summary);
        $this->assertSame('Cumplimiento promedio', $summary['label']);
    }
}
