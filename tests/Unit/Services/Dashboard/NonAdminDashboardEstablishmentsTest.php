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
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class NonAdminDashboardEstablishmentsTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    public function test_establishments_panel_lists_all_evaluated_centers_with_indicator_average_compliance(): void
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

        $centers = [];
        for ($i = 1; $i <= 6; $i++) {
            $centers[] = $this->createMinimalCenCenter(['nombre_corto' => 'Centro '.$i]);
        }

        $metIndicatorA = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $metasType->id,
            'year_id' => $year->id,
            'clave' => 'MT-A',
            'nombre' => 'Indicador A',
            'meta_valor' => 100,
            'meta_operador' => MetaOperator::MayorIgual,
            'estado' => true,
            'orden' => 1,
        ]));

        $metIndicatorB = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $metasType->id,
            'year_id' => $year->id,
            'clave' => 'MT-B',
            'nombre' => 'Indicador B',
            'meta_valor' => 100,
            'meta_operador' => MetaOperator::MayorIgual,
            'estado' => true,
            'orden' => 2,
        ]));

        foreach ($centers as $index => $center) {
            MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
                'cen_center_id' => $center->id,
                'year_id' => $year->id,
                'indicator_type_id' => $metasType->id,
                'cen_hie_dependency_id' => 1,
            ]));

            if ($index === 0) {
                foreach ([$metIndicatorA, $metIndicatorB] as $indicator) {
                    MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                        'met_indicator_id' => $indicator->id,
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
                    'met_indicator_id' => $metIndicatorA->id,
                    'cen_center_id' => $center->id,
                    'cen_hie_dependency_id' => 1,
                    'year_id' => $year->id,
                    'periodo' => 'ANUAL',
                    'resultado' => 80,
                    'etapa' => 'AUTOEVALUACION',
                ]));

                MetResult::unguarded(fn () => MetResult::create([
                    'met_indicator_id' => $metIndicatorB->id,
                    'cen_center_id' => $center->id,
                    'cen_hie_dependency_id' => 1,
                    'year_id' => $year->id,
                    'periodo' => 'ANUAL',
                    'resultado' => 60,
                    'etapa' => 'AUTOEVALUACION',
                ]));
            }

            if ($index === 1) {
                MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                    'met_indicator_id' => $metIndicatorA->id,
                    'year_id' => $year->id,
                    'cen_hie_dependency_id' => 1,
                    'cen_center_id' => $center->id,
                    'seleccionado' => true,
                    'aplica' => true,
                    'meta_efectiva' => 100,
                    'ponderacion' => 100,
                ]));

                MetResult::unguarded(fn () => MetResult::create([
                    'met_indicator_id' => $metIndicatorA->id,
                    'cen_center_id' => $center->id,
                    'cen_hie_dependency_id' => 1,
                    'year_id' => $year->id,
                    'periodo' => 'ANUAL',
                    'resultado' => 90,
                    'etapa' => 'AUTOEVALUACION',
                ]));
            }
        }

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $service = app(NonAdminDashboardService::class);
        $establishments = $service->forUser($user)['top_establecimientos'] ?? [];

        $this->assertCount(6, $establishments);
        $this->assertSame(90.0, $establishments[0]['percent']);
        $this->assertSame('Centro 2', $establishments[0]['center_label']);
        $this->assertSame(70.0, $establishments[1]['percent']);
        $this->assertSame('Centro 1', $establishments[1]['center_label']);
        $this->assertNull($establishments[2]['percent']);
        $this->assertSame(
            route('centinela.dashboard.detail', ['section' => 'establecimientos', 'slug' => $centers[0]->id]),
            $establishments[1]['detail_url'],
        );

        $detailRows = $service->detailRows($user, 'establecimientos', (string) $centers[0]->id);
        $indicatorRows = collect($detailRows)->reject(fn (array $row): bool => ! empty($row['is_summary']));
        $this->assertCount(2, $indicatorRows);
        $this->assertSame(80.0, $indicatorRows->firstWhere('label', '[MT-A] Indicador A')['percent']);
        $this->assertSame(60.0, $indicatorRows->firstWhere('label', '[MT-B] Indicador B')['percent']);
        $this->assertSame(70.0, collect($detailRows)->firstWhere('is_summary', true)['percent']);
    }
}
