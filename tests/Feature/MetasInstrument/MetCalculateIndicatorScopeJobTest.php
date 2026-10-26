<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Jobs\MetCalculateIndicatorScopeJob;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetAutoevaluacionCalculationRunner;
use App\Services\Metas\MetRecalculationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetCalculateIndicatorScopeJobTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private CenCenter $center;

    private MetIndicator $indicator;

    private MetDataField $fieldA;

    private MetDataField $fieldB;

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

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($adminRole)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $this->createCenterCatalogDependencies();

        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-SCOPE-01',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Scope Test',
            'nombre_corto' => 'HospScope',
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

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-SCOPE-01',
            'nombre' => 'Indicador scope',
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

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => true,
            'ponderacion' => 100,
        ]));
    }

    /** @test */
    public function scope_job_persiste_resultado_tras_carga_mensual(): void
    {
        $this->createMonthlyData($this->fieldA->id, [3 => 30]);
        $this->createMonthlyData($this->fieldB->id, [3 => 60]);

        $scope = new MetRecalculationScope(
            yearId: $this->year->id,
            dependencyId: 1,
            indicatorTypeId: $this->indicatorType->id,
            centerId: $this->center->id,
            metIndicatorId: $this->indicator->id,
        );

        (new MetCalculateIndicatorScopeJob($scope))->handle(app(MetAutoevaluacionCalculationRunner::class));

        $this->assertDatabaseHas('met_results', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'periodo' => 'MES-03',
            'etapa' => 'AUTOEVALUACION',
        ]);

        $result = MetResult::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->where('periodo', 'MES-03')
            ->first();

        $this->assertNotNull($result);
        $this->assertSame('50.0000', number_format((float) $result->resultado, 4, '.', ''));
    }

    private function createCenterCatalogDependencies(): void {}

    /**
     * @param  array<int, float|int>  $valuesByMonth
     */
    private function createMonthlyData(int $fieldId, array $valuesByMonth): void
    {
        foreach ($valuesByMonth as $mes => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fieldId,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => $mes,
                'valor' => $valor,
            ]));
        }
    }
}
