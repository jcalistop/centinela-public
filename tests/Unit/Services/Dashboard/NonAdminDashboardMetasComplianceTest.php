<?php

namespace Tests\Unit\Services\Dashboard;

use App\Enums\MetaOperator;
use App\Livewire\Dashboard\NonAdminDashboardPanel;
use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class NonAdminDashboardMetasComplianceTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    private function attachBrowseAdmin(Role $role): void
    {
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    private function enableDashboardInstrument(IndicatorType $type, Year $year, string $moduleKey): void
    {
        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => $moduleKey,
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));
    }

    public function test_metas_detail_rows_average_compliance_across_establishments(): void
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

        $this->enableDashboardInstrument($indicatorType, $year, 'metas-18834');

        $centerA = $this->createMinimalCenCenter(['nombre_corto' => 'Centro A']);
        $centerB = $this->createMinimalCenCenter(['nombre_corto' => 'Centro B']);

        $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $year->id,
            'clave' => 'MT-01',
            'nombre' => 'Indicador Metas prueba',
            'meta_valor' => 100,
            'meta_operador' => MetaOperator::MayorIgual,
            'estado' => true,
            'orden' => 1,
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
        }

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $metIndicator->id,
            'cen_center_id' => $centerA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $year->id,
            'periodo' => 'ANUAL',
            'resultado' => 50,
            'etapa' => 'AUTOEVALUACION',
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $metIndicator->id,
            'cen_center_id' => $centerB->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $year->id,
            'periodo' => 'ANUAL',
            'resultado' => 70,
            'etapa' => 'AUTOEVALUACION',
        ]));

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $rows = app(NonAdminDashboardService::class)->detailRows($user, 'ambitos', 'sin-ambito');
        $metRow = collect($rows)->firstWhere('source', 'Metas');

        $this->assertNotNull($metRow);
        $this->assertSame('Promedio de cumplimiento', $metRow['establishment']);

        $desempeno = app(MetIndicatorDesempenoByEstablishmentService::class)
            ->buildForIndicator($metIndicator, 1, $year->id);
        $this->assertSame($desempeno['promedio_cumplimiento'], $metRow['percent']);
        $this->assertSame(60.0, $metRow['percent']);
        $this->assertSame(
            route('metas.instrument.indicators.show', [
                'indicatorType' => $indicatorType->route,
                'indicator' => $metIndicator,
            ]),
            $metRow['url'],
        );
    }

    public function test_comges_detail_rows_link_to_indicator_show(): void
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

        $comgesType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 1,
            ],
        ));
        $this->enableDashboardInstrument($comgesType, $year, 'comges');

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'year_id' => $year->id,
            'clave' => 'C1',
            'nombre' => 'Compromiso',
            'orden' => 1,
        ]));

        $comIndicator = ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'COMGES 2.16',
            'nombre' => 'Indicador COMGES prueba',
            'ponderacion_indicador' => 100,
        ]));

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $rows = app(NonAdminDashboardService::class)->detailRows($user, 'ambitos', 'sin-ambito');
        $comRow = collect($rows)->firstWhere('source', 'COMGES');

        $this->assertNotNull($comRow);
        $this->assertSame('Última evaluación', $comRow['establishment']);
        $this->assertSame(
            route('voyager.indicadores-comges.show', $comIndicator->id),
            $comRow['url'],
        );
    }

    public function test_metas_detail_percent_matches_desempeno_when_establishment_aplica_but_not_convenido(): void
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

        $this->enableDashboardInstrument($indicatorType, $year, 'metas-18834');

        $centerConvenido = $this->createMinimalCenCenter(['nombre_corto' => 'Centro convenido']);
        $centerNoConvenido = $this->createMinimalCenCenter(['nombre_corto' => 'Centro no convenido']);

        $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $year->id,
            'clave' => 'MT-02',
            'nombre' => 'Indicador aplica sin convenio',
            'meta_valor' => 100,
            'meta_operador' => MetaOperator::MayorIgual,
            'estado' => true,
            'orden' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $metIndicator->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $centerConvenido->id,
            'seleccionado' => true,
            'aplica' => true,
            'meta_efectiva' => 100,
            'ponderacion' => 100,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $metIndicator->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $centerNoConvenido->id,
            'seleccionado' => false,
            'aplica' => true,
            'meta_efectiva' => 100,
            'ponderacion' => 100,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $metIndicator->id,
            'cen_center_id' => $centerConvenido->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $year->id,
            'periodo' => 'ANUAL',
            'resultado' => 40,
            'etapa' => 'AUTOEVALUACION',
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $metIndicator->id,
            'cen_center_id' => $centerNoConvenido->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $year->id,
            'periodo' => 'ANUAL',
            'resultado' => 80,
            'etapa' => 'AUTOEVALUACION',
        ]));

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $rows = app(NonAdminDashboardService::class)->detailRows($user, 'ambitos', 'sin-ambito');
        $metRow = collect($rows)->firstWhere('source', 'Metas');

        $desempeno = app(MetIndicatorDesempenoByEstablishmentService::class)
            ->buildForIndicator($metIndicator, 1, $year->id);

        $this->assertNotNull($metRow);
        $this->assertSame($desempeno['promedio_cumplimiento'], $metRow['percent']);
        $this->assertSame(60.0, $metRow['percent']);
    }

    public function test_ambito_detail_rows_include_average_summary_row(): void
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

        $this->enableDashboardInstrument($indicatorType, $year, 'metas-18834');

        $center = $this->createMinimalCenCenter(['nombre_corto' => 'Centro A']);

        foreach (['MT-A' => 40.0, 'MT-B' => 80.0] as $clave => $resultado) {
            $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $indicatorType->id,
                'year_id' => $year->id,
                'clave' => $clave,
                'nombre' => 'Indicador '.$clave,
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
                'resultado' => $resultado,
                'etapa' => 'AUTOEVALUACION',
            ]));
        }

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $rows = app(NonAdminDashboardService::class)->detailRows($user, 'ambitos', 'sin-ambito');
        $summary = collect($rows)->firstWhere('is_summary', true);

        $this->assertNotNull($summary);
        $this->assertSame('Cumplimiento promedio', $summary['label']);
        $this->assertSame(60.0, $summary['percent']);
        $this->assertNull($summary['url']);
    }

    public function test_ambito_widget_percent_matches_detail_summary(): void
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

        $this->enableDashboardInstrument($indicatorType, $year, 'metas-18834');

        $centerConvenido = $this->createMinimalCenCenter(['nombre_corto' => 'Centro convenido']);
        $centerNoConvenido = $this->createMinimalCenCenter(['nombre_corto' => 'Centro no convenido']);

        $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $year->id,
            'clave' => 'MT-WIDGET',
            'nombre' => 'Indicador widget ámbito',
            'meta_valor' => 100,
            'meta_operador' => MetaOperator::MayorIgual,
            'estado' => true,
            'orden' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $metIndicator->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $centerConvenido->id,
            'seleccionado' => true,
            'aplica' => true,
            'meta_efectiva' => 100,
            'ponderacion' => 100,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $metIndicator->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $centerNoConvenido->id,
            'seleccionado' => false,
            'aplica' => true,
            'meta_efectiva' => 100,
            'ponderacion' => 100,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $metIndicator->id,
            'cen_center_id' => $centerConvenido->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $year->id,
            'periodo' => 'ANUAL',
            'resultado' => 40,
            'etapa' => 'AUTOEVALUACION',
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $metIndicator->id,
            'cen_center_id' => $centerNoConvenido->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $year->id,
            'periodo' => 'ANUAL',
            'resultado' => 80,
            'etapa' => 'AUTOEVALUACION',
        ]));

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $service = app(NonAdminDashboardService::class);
        $payload = $service->forUser($user);
        $widget = collect($payload['ambitos'])->firstWhere('slug', 'sin-ambito');
        $summary = collect($service->detailRows($user, 'ambitos', 'sin-ambito'))
            ->firstWhere('is_summary', true);

        $this->assertNotNull($widget);
        $this->assertNotNull($summary);
        $this->assertSame(60.0, $widget['percent']);
        $this->assertSame($widget['percent'], $summary['percent']);
    }

    public function test_ambito_detail_renders_semaforo_badges_and_legend(): void
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
        $this->attachBrowseAdmin($role);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        config()->set('centinela.dashboard_non_admin.enabled', true);

        $indicatorType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'metas-18834',
                'nombre_indicador' => 'Metas 18834',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        $this->enableDashboardInstrument($indicatorType, $year, 'metas-18834');

        $center = $this->createMinimalCenCenter(['nombre_corto' => 'Centro A']);

        $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $year->id,
            'clave' => 'MT-SEM',
            'nombre' => 'Indicador semáforo',
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

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('centinela.dashboard.detail', ['section' => 'ambitos', 'slug' => 'sin-ambito']))
            ->assertOk()
            ->assertSee('Promedio de cumplimiento', false)
            ->assertSee('semaforo orange', false)
            ->assertSee('100% — Cumplimiento total', false)
            ->assertSee('S/I — Sin información', false);

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(NonAdminDashboardPanel::class)
            ->assertSee('100% — Cumplimiento total', false)
            ->assertSee('semaforo orange', false);
    }
}
