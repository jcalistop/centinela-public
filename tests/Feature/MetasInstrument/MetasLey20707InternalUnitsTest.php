<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\MetUnitType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\MetCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetasLey20707InternalUnitsTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private CenCenter $center;

    private MetIndicator $indicator;

    private MetDataField $fieldA;

    private MetDataField $fieldB;

    private MetMeasurementUnit $unitA;

    private MetMeasurementUnit $unitB;

    private MetUnitType $unitType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->indicatorType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-20707-test',
            'nombre_indicador' => 'Metas 20.707 Test',
            'route' => 'metas-20707-test',
            'view' => null,
            'estado' => true,
            'orden' => 99,
            'measurement_granularity' => 'internal_unit',
        ]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($adminRole)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $this->createCenterCatalogDependencies();

        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-20707',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital 20707',
            'nombre_corto' => 'H20707',
            'cen_certification_id' => 1,
            'cen_adm_dependency_id' => 1,
            'nivel_atencion' => 'Primario',
            'tiene_urgencia' => 'No',
            'cen_emergency_type_id' => 1,
            'tipo_sapu' => 'No Aplica',
            'cen_provider_type_id' => 1,
            'estado_funcionamiento' => CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
            'nivel_complejidad' => 'Baja',
            'tipo_atencion' => 'Abierta',
        ]));

        $this->unitType = MetUnitType::unguarded(fn () => MetUnitType::create([
            'codigo' => 'TTEST',
            'nombre' => 'Tipo test',
            'orden' => 1,
        ]));

        $this->unitA = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $this->unitType->id,
            'codigo' => 'U-A',
            'nombre' => 'Unidad A',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->unitB = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $this->unitType->id,
            'codigo' => 'U-B',
            'nombre' => 'Unidad B',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'L707-01',
            'nombre' => 'Indicador 20707',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 40,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        $this->fieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        $this->fieldB = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'orden' => 1,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
            'description' => '(A / B) * 100',
        ]));

        foreach ([$this->unitA, $this->unitB] as $unit) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $this->indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $this->center->id,
                'met_measurement_unit_id' => $unit->id,
                'seleccionado' => true,
                'ponderacion' => 100,
                'aplica' => true,
            ]));
        }
    }

    /** @test */
    public function dos_unidades_producen_dos_met_results_para_el_mismo_periodo(): void
    {
        $this->createMonthlyForUnit($this->unitA->id, [1 => 10, 2 => 20, 3 => 30], [1 => 20, 2 => 40, 3 => 60]);
        $this->createMonthlyForUnit($this->unitB->id, [1 => 5, 2 => 5, 3 => 10], [1 => 10, 2 => 10, 3 => 20]);

        $service = app(MetCalculationService::class);

        $rA = $service->calculateIndicatorPeriod(
            $this->indicator,
            $this->center->id,
            $this->year->id,
            1,
            'MES-03',
            [],
            $this->unitA->id,
        );

        $rB = $service->calculateIndicatorPeriod(
            $this->indicator,
            $this->center->id,
            $this->year->id,
            1,
            'MES-03',
            [],
            $this->unitB->id,
        );

        $this->assertSame((int) $this->unitA->id, (int) $rA->met_measurement_unit_id);
        $this->assertSame((int) $this->unitB->id, (int) $rB->met_measurement_unit_id);
        $this->assertSame('50.0000', number_format((float) $rA->resultado, 4, '.', ''));
        $this->assertSame('50.0000', number_format((float) $rB->resultado, 4, '.', ''));

        $this->assertSame(2, MetResult::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->where('year_id', $this->year->id)
            ->where('periodo', 'MES-03')
            ->whereNotNull('met_measurement_unit_id')
            ->count());
    }

    /** @test */
    public function resultado_global_establecimiento_es_promedio_de_unidades(): void
    {
        $this->createMonthlyForUnit($this->unitA->id, [1 => 10, 2 => 20, 3 => 30], [1 => 20, 2 => 40, 3 => 60]);
        $this->createMonthlyForUnit($this->unitB->id, [1 => 10, 2 => 20, 3 => 30], [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);

        $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-03', [], $this->unitA->id);
        $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-03', [], $this->unitB->id);

        $est = $service->calculateEstablishmentResult(
            $this->center->id,
            $this->year->id,
            1,
            'MES-03',
            $this->indicatorType->id,
        );

        $this->assertSame(2, $est->n_calculados);
        $this->assertSame('100.0000', number_format((float) $est->resultado_global, 4, '.', ''));
    }

    private function createMonthlyForUnit(int $unitId, array $aByMonth, array $bByMonth): void
    {
        foreach ($aByMonth as $m => $v) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $this->fieldA->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'met_measurement_unit_id' => $unitId,
                'mes' => $m,
                'valor' => $v,
            ]));
        }
        foreach ($bByMonth as $m => $v) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $this->fieldB->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'met_measurement_unit_id' => $unitId,
                'mes' => $m,
                'valor' => $v,
            ]));
        }
    }

    private function createCenterCatalogDependencies(): void {}
}
