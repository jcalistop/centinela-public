<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\IndicatorType\EtapaVigente;
use App\Jobs\MetCalculateResultsJob;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetEstablishmentResult;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetAutoevaluacionCalculationRunner;
use App\Services\Metas\MetStageCalculationService;
use App\Services\MetCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetCalculateResultsJobPurgeTest extends TestCase
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
            'codigo' => 'CEN-001',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Test',
            'nombre_corto' => 'HospTest',
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

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-PURGE-01',
            'nombre' => 'Indicador purge',
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
            'seleccionado' => true,
            'ponderacion' => 100,
        ]));
    }

    /** @test */
    public function job_elimina_resultados_con_periodo_obsoleto(): void
    {
        foreach ([1 => 10, 2 => 20, 3 => 30] as $mes => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $this->fieldA->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => $mes,
                'valor' => $valor,
            ]));
        }
        foreach ([1 => 20, 2 => 40, 3 => 60] as $mes => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $this->fieldB->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => $mes,
                'valor' => $valor,
            ]));
        }

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'TRIM-1',
            'numerador' => 1,
            'denominador' => 1,
            'resultado' => 1,
            'cumple_meta' => false,
            'error_message' => null,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'TRIM-1',
            'resultado_global' => 99,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        (new MetCalculateResultsJob($this->year->id, 1, null, EtapaVigente::Autoevaluacion->value))->handle(
            app(MetCalculationService::class),
            app(MetStageCalculationService::class),
            app(MetAutoevaluacionCalculationRunner::class),
        );

        $this->assertDatabaseMissing('met_results', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'periodo' => 'TRIM-1',
        ]);

        $this->assertDatabaseHas('met_results', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'periodo' => 'MES-03',
        ]);

        $this->assertDatabaseMissing('met_establishment_results', [
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'TRIM-1',
        ]);

        $this->assertDatabaseHas('met_establishment_results', [
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'MES-03',
        ]);

        $this->assertDatabaseHas('met_establishment_results', [
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'ANUAL',
        ]);
    }

    /** @test */
    public function job_calcula_indicador_aplicable_aunque_no_este_convenido(): void
    {
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update([
                'aplica' => true,
                'seleccionado' => false,
                'ponderacion' => null,
            ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldB->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 20,
        ]));

        (new MetCalculateResultsJob($this->year->id, 1, null, EtapaVigente::Autoevaluacion->value))->handle(
            app(MetCalculationService::class),
            app(MetStageCalculationService::class),
            app(MetAutoevaluacionCalculationRunner::class),
        );

        $this->assertDatabaseHas('met_results', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'periodo' => 'MES-01',
        ]);
    }

    private function createCenterCatalogDependencies(): void {}
}
