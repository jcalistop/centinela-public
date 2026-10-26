<?php

namespace Tests\Unit\Services\Metas;

use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\MetUnitType;
use App\Models\Year;
use App\Services\Metas\MetEstablishmentIndicatorConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetEstablishmentIndicatorConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    private IndicatorType $indicatorType;

    private Year $year;

    private CenCenter $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->center = $this->createMinimalCenCenter([
            'id' => 10,
            'codigo' => 'CEN-10',
            'nombre_corto' => 'Hospital Test',
            'cen_hie_dependency_id' => 1,
        ]);
    }

    /** @test */
    public function detecta_establecimiento_sin_configuracion(): void
    {
        $service = app(MetEstablishmentIndicatorConfigService::class);

        $issues = $service->issuesByCenterId(
            (int) $this->indicatorType->id,
            [(int) $this->center->id],
            (int) $this->year->id,
            1,
            false,
        );

        $this->assertTrue($issues->has((int) $this->center->id));
        $this->assertStringContainsString('Sin indicadores en convenio', $issues[(int) $this->center->id]['message']);
    }

    /** @test */
    public function detecta_ponderacion_incompleta(): void
    {
        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 2,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        $indicators = collect(range(1, 2))->map(fn (int $n) => MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MET-UT-'.$n,
            'nombre' => 'Indicador '.$n,
            'estado' => true,
            'orden' => $n,
            'meta_valor' => 50,
            'meta_operador' => '>=',
        ])));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'seleccionado' => true,
            'ponderacion' => 60,
            'aplica' => true,
            'meta_efectiva' => 80,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicators[1]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'seleccionado' => true,
            'ponderacion' => 30,
            'aplica' => true,
            'meta_efectiva' => null,
        ]));

        $service = app(MetEstablishmentIndicatorConfigService::class);
        $issues = $service->issuesByCenterId(
            (int) $this->indicatorType->id,
            [(int) $this->center->id],
            (int) $this->year->id,
            1,
            false,
        );

        $this->assertTrue($issues->has((int) $this->center->id));
        $this->assertContains(MetEstablishmentIndicatorConfigService::ISSUE_PONDERACION, $issues[(int) $this->center->id]['types']);
    }

    /** @test */
    public function acepta_configuracion_completa(): void
    {
        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 2,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        foreach (range(1, 2) as $index => $n) {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'clave' => 'MET-OK-'.$n,
                'nombre' => 'Indicador OK '.$n,
                'estado' => true,
                'orden' => $n,
                'meta_valor' => 50,
                'meta_operador' => '>=',
            ]));

            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $this->center->id,
                'seleccionado' => true,
                'ponderacion' => 50,
                'aplica' => true,
                'meta_efectiva' => 70 + $index,
            ]));
        }

        $service = app(MetEstablishmentIndicatorConfigService::class);
        $issues = $service->issuesByCenterId(
            (int) $this->indicatorType->id,
            [(int) $this->center->id],
            (int) $this->year->id,
            1,
            false,
        );

        $this->assertTrue($issues->isEmpty());
    }

    /** @test */
    public function no_mezcla_configuraciones_de_otro_instrumento_en_el_mismo_establecimiento(): void
    {
        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 2,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        $otherType = IndicatorType::query()->where('route', 'metas-19664')->firstOrFail();

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $otherType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 2,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        foreach (range(1, 2) as $n) {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'clave' => 'MET-AISL-'.$n,
                'nombre' => 'Indicador aislado '.$n,
                'estado' => true,
                'orden' => $n,
                'meta_valor' => 50,
                'meta_operador' => '>=',
            ]));

            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $this->center->id,
                'seleccionado' => true,
                'ponderacion' => 50,
                'aplica' => true,
                'meta_efectiva' => 70,
            ]));
        }

        $otherIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $otherType->id,
            'year_id' => $this->year->id,
            'clave' => 'MET-OTRO-1',
            'nombre' => 'Indicador otro instrumento',
            'estado' => true,
            'orden' => 1,
            'meta_valor' => 50,
            'meta_operador' => '>=',
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $otherIndicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'seleccionado' => true,
            'ponderacion' => null,
            'aplica' => true,
            'meta_efectiva' => null,
        ]));

        $service = app(MetEstablishmentIndicatorConfigService::class);
        $issues = $service->issuesByCenterId(
            (int) $this->indicatorType->id,
            [(int) $this->center->id],
            (int) $this->year->id,
            1,
            false,
        );

        $this->assertTrue($issues->isEmpty());
    }

    /** @test */
    public function ignora_convenido_obsoleto_con_aplica_false(): void
    {
        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 2,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        foreach (range(1, 2) as $n) {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'clave' => 'MET-APL-'.$n,
                'nombre' => 'Indicador aplica '.$n,
                'estado' => true,
                'orden' => $n,
                'meta_valor' => 50,
                'meta_operador' => '>=',
            ]));

            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $this->center->id,
                'seleccionado' => true,
                'ponderacion' => 50,
                'aplica' => true,
                'meta_efectiva' => 70,
            ]));
        }

        $staleIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MET-STALE',
            'nombre' => 'Indicador obsoleto',
            'estado' => true,
            'orden' => 3,
            'meta_valor' => 50,
            'meta_operador' => '>=',
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $staleIndicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'seleccionado' => true,
            'ponderacion' => null,
            'aplica' => false,
            'meta_efectiva' => null,
        ]));

        $service = app(MetEstablishmentIndicatorConfigService::class);
        $issues = $service->issuesByCenterId(
            (int) $this->indicatorType->id,
            [(int) $this->center->id],
            (int) $this->year->id,
            1,
            false,
        );

        $this->assertTrue($issues->isEmpty());
    }

    /** @test */
    public function internal_unit_agrega_detalle_por_unidad_y_mensaje_agregado(): void
    {
        $internalType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-20707-ut',
            'nombre_indicador' => 'Metas 20.707 UT',
            'route' => 'metas-20707-ut',
            'view' => null,
            'estado' => true,
            'orden' => 99,
            'measurement_granularity' => 'internal_unit',
        ]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $internalType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 2,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        $unitType = MetUnitType::create([
            'codigo' => 'UT-T',
            'nombre' => 'Tipo test',
            'orden' => 1,
            'enabled' => true,
        ]);

        $unitOk = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'URG-1',
            'nombre' => 'Urgencias',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $unitBad = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'PAB-3',
            'nombre' => 'Pabellón',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        foreach (range(1, 2) as $n) {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $internalType->id,
                'year_id' => $this->year->id,
                'clave' => 'MET-IU-'.$n,
                'nombre' => 'Indicador '.$n,
                'estado' => true,
                'orden' => $n,
                'meta_valor' => 50,
                'meta_operador' => '>=',
            ]));

            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $this->center->id,
                'met_measurement_unit_id' => $unitOk->id,
                'seleccionado' => true,
                'ponderacion' => 50,
                'aplica' => true,
                'meta_efectiva' => 70,
            ]));

            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $this->center->id,
                'met_measurement_unit_id' => $unitBad->id,
                'seleccionado' => true,
                'ponderacion' => 30,
                'aplica' => true,
                'meta_efectiva' => 70,
            ]));
        }

        $service = app(MetEstablishmentIndicatorConfigService::class);
        $issues = $service->issuesByCenterId(
            (int) $internalType->id,
            [(int) $this->center->id],
            (int) $this->year->id,
            1,
            true,
        );

        $this->assertTrue($issues->has((int) $this->center->id));
        $issue = $issues[(int) $this->center->id];
        $this->assertStringContainsString('1 unidad incompleta', $issue['message']);
        $this->assertStringContainsString('PAB-3', $issue['message']);
        $this->assertArrayHasKey('units', $issue);
        $this->assertCount(1, $issue['units']);
        $this->assertSame('PAB-3', $issue['units'][0]['codigo']);
    }

    /** @test */
    public function unit_statuses_for_center_marca_completa_e_incompleta(): void
    {
        $internalType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-20707-st',
            'nombre_indicador' => 'Metas 20.707 ST',
            'route' => 'metas-20707-st',
            'view' => null,
            'estado' => true,
            'orden' => 98,
            'measurement_granularity' => 'internal_unit',
        ]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $internalType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 2,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        $unitType = MetUnitType::create([
            'codigo' => 'UT-ST',
            'nombre' => 'Tipo ST',
            'orden' => 1,
            'enabled' => true,
        ]);

        $unitOk = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'OK-1',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $unitBad = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'BAD-1',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        foreach (range(1, 2) as $n) {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $internalType->id,
                'year_id' => $this->year->id,
                'clave' => 'MET-ST-'.$n,
                'nombre' => 'Indicador ST '.$n,
                'estado' => true,
                'orden' => $n,
                'meta_valor' => 50,
                'meta_operador' => '>=',
            ]));

            foreach ([$unitOk, $unitBad] as $unit) {
                MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                    'met_indicator_id' => $indicator->id,
                    'year_id' => $this->year->id,
                    'cen_hie_dependency_id' => 1,
                    'cen_center_id' => $this->center->id,
                    'met_measurement_unit_id' => $unit->id,
                    'seleccionado' => true,
                    'ponderacion' => $unit->id === $unitOk->id ? 50 : 30,
                    'aplica' => true,
                    'meta_efectiva' => 70,
                ]));
            }
        }

        $service = app(MetEstablishmentIndicatorConfigService::class);
        $statuses = $service->unitStatusesForCenter(
            (int) $internalType->id,
            (int) $this->center->id,
            (int) $this->year->id,
            1,
        );

        $this->assertTrue($statuses->firstWhere('codigo', 'OK-1')['is_complete']);
        $this->assertFalse($statuses->firstWhere('codigo', 'BAD-1')['is_complete']);
    }
}
