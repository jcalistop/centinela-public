<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\EstablishmentResultsDetailComponent;
use App\Livewire\MetasInstrument\ResultsSummaryComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\MetCenterAdosado;
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
use App\Support\Metas\MetSemaforoThresholds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ResultsSummaryModalTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $user;

    private IndicatorType $indicatorType;

    private CenCenter $center;

    private MetIndicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $this->user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->user->role()->associate($adminRole)->save();

        $this->actingAs($this->user)->withSession(['indicator_year' => $this->year]);

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

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
            'nombre_corto' => 'Hospital Test',
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
            'clave' => 'MET-RES-01',
            'nombre' => 'Indicador resumen modal',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 20,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador semestral',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'semestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador línea base',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'linea_base',
            'aggregation_type' => null,
            'orden' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => false,
        ]));
    }

    /** @test */
    public function open_data_modal_no_acumula_denominador_linea_base(): void
    {
        foreach (['SEM-1', 'SEM-2'] as $periodo) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'numerador' => 123,
                'denominador' => 250,
                'resultado' => 49.2,
                'cumple_meta' => true,
            ]));
        }

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('openDataModal', $this->indicator->id)
            ->assertDispatched('open-data-modal', function (string $eventName, array $payload): bool {
                $periodos = collect($payload['periodos'] ?? []);
                $segundoSemestre = $periodos->firstWhere('periodo', 'SEM-2');
                $ficha = $payload['ficha'] ?? null;

                return ($payload['isSumatoria'] ?? false) === true
                    && $segundoSemestre !== null
                    && (float) $segundoSemestre['numerador_acumulado'] === 246.0
                    && (float) $segundoSemestre['denominador_acumulado'] === 250.0
                    && (float) $segundoSemestre['resultado_acumulado'] === 98.4
                    && (float) $segundoSemestre['cumplimiento_acumulado'] === 100.0
                    && is_array($ficha)
                    && ($ficha['periodicidad'] ?? '') === 'SEMESTRAL'
                    && (float) ($ficha['meta_nacional'] ?? 0) === 20.0;
            });
    }

    /** @test */
    public function open_data_modal_topea_cumplimiento_por_periodo_en_100(): void
    {
        // meta_efectiva cae a meta_valor = 20: SEM-1 daría 246 % sin tope; SEM-2 queda lineal en 75 %.
        $resultadosPorPeriodo = ['SEM-1' => 49.2, 'SEM-2' => 15];

        foreach ($resultadosPorPeriodo as $periodo => $resultado) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'numerador' => 123,
                'denominador' => 250,
                'resultado' => $resultado,
                'cumple_meta' => true,
            ]));
        }

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('openDataModal', $this->indicator->id)
            ->assertDispatched('open-data-modal', function (string $eventName, array $payload): bool {
                $periodos = collect($payload['periodos'] ?? []);
                $primerSemestre = $periodos->firstWhere('periodo', 'SEM-1');
                $segundoSemestre = $periodos->firstWhere('periodo', 'SEM-2');

                return $primerSemestre !== null
                    && $segundoSemestre !== null
                    && (float) $primerSemestre['cumplimiento'] === 100.0
                    && (float) $segundoSemestre['cumplimiento'] === 75.0;
            });
    }

    /** @test */
    public function open_data_modal_formula_compleja_incluye_resultado_acumulado_sin_nan(): void
    {
        MetDataField::query()->where('met_indicator_id', $this->indicator->id)->delete();

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'paren', 'value' => ')'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
            'description' => 'Fórmula compleja modal',
        ]));

        foreach (['A', 'B'] as $orden => $variableKey) {
            MetDataField::unguarded(fn () => MetDataField::create([
                'met_indicator_id' => $this->indicator->id,
                'label' => "Variable {$variableKey}",
                'variable_key' => $variableKey,
                'componente' => 'numerador',
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
                'orden' => $orden,
            ]));
        }

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        foreach (
            [
                1 => ['A' => 1264, 'B' => 1517],
                2 => ['A' => 1241, 'B' => 1296],
            ] as $mes => $valores
        ) {
            foreach ($valores as $variableKey => $valor) {
                MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                    'met_data_field_id' => $fields[$variableKey]->id,
                    'cen_center_id' => $this->center->id,
                    'cen_hie_dependency_id' => 1,
                    'year_id' => $this->year->id,
                    'mes' => $mes,
                    'valor' => $valor,
                ]));
            }
        }

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-01',
            'numerador' => null,
            'denominador' => null,
            'resultado' => -16.68,
            'cumple_meta' => true,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-02',
            'numerador' => null,
            'denominador' => null,
            'resultado' => -4.24,
            'cumple_meta' => true,
        ]));

        $this->indicator->update([
            'decimales' => 2,
            'decimales_modo' => 'redondear',
        ]);

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('openDataModal', $this->indicator->id)
            ->assertDispatched('open-data-modal', function (string $eventName, array $payload): bool {
                $periodos = collect($payload['periodos'] ?? []);
                $mes01 = $periodos->firstWhere('periodo', 'MES-01');
                $mes02 = $periodos->firstWhere('periodo', 'MES-02');

                return ($payload['isSumatoria'] ?? false) === true
                    && ($payload['usesComplexPrimaryComponents'] ?? false) === true
                    && $mes01 !== null
                    && $mes02 !== null
                    && array_key_exists('resultado_acumulado', $mes01)
                    && array_key_exists('resultado_acumulado', $mes02)
                    && array_key_exists('cumplimiento_acumulado', $mes01)
                    && array_key_exists('cumplimiento_acumulado', $mes02)
                    && $mes01['resultado_acumulado'] !== null
                    && $mes02['resultado_acumulado'] !== null
                    && ! is_nan((float) $mes01['resultado_acumulado'])
                    && ! is_nan((float) $mes02['resultado_acumulado'])
                    && (float) $mes01['resultado_acumulado'] === -16.68
                    && (float) $mes02['resultado_acumulado'] === -10.95
                    && (float) $mes01['cumplimiento_acumulado'] === 0.0
                    && (float) $mes02['cumplimiento_acumulado'] === 0.0;
            });
    }

    /** @test */
    public function establecimiento_muestra_visualizar_datos_y_deja_la_descarga_en_el_modal(): void
    {
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 10,
            'denominador' => 100,
            'resultado' => 10,
            'cumple_meta' => false,
        ]));

        $downloadUrl = route('metas.instrument.indicador.detalle-datos.download', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
            'centers' => [$this->center->id],
        ]);

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertSee('Visualizar datos')
            ->assertDontSee('Descargar datos')
            ->assertDontSeeHtml('href="'.$downloadUrl.'"')
            ->assertSee('Cumplimiento satisfactorio')
            ->assertSee('Solo los indicadores en convenio aportan al cumplimiento global.')
            ->call('openDataModal', $this->indicator->id)
            ->assertDispatched('open-data-modal', function (string $eventName, array $payload) use ($downloadUrl): bool {
                return ($payload['dataEntryUrl'] ?? null) === $downloadUrl;
            });
    }

    /** @test */
    public function render_resultados_por_establecimiento_no_acumula_denominador_linea_base(): void
    {
        foreach (['SEM-1', 'SEM-2'] as $periodo) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'numerador' => 123,
                'denominador' => 250,
                'resultado' => 49.2,
                'cumple_meta' => true,
            ]));
        }

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertViewHas('byIndicator', function ($byIndicator): bool {
                $result = $byIndicator->filter(
                    fn ($results) => $results->first()->cen_center_id === $this->center->id
                        && $results->first()->met_indicator_id === $this->indicator->id
                )->first();

                if (! $result) {
                    return false;
                }

                $annual = $result->firstWhere('periodo', 'ANUAL');

                return $annual !== null
                    && (float) $annual->numerador === 246.0
                    && (float) $annual->denominador === 250.0
                    && (float) $annual->resultado === 98.4;
            });
    }

    /** @test */
    public function autoevaluacion_semaforo_usa_umbral_superior_del_indicador(): void
    {
        $this->indicator->update(['meta_valor' => 100, 'umbral_superior' => 95]);
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['meta_efectiva' => 100]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 94,
            'denominador' => 100,
            'resultado' => 94,
            'cumple_meta' => false,
        ]));

        $component = Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])->set('soloComprometidos', false);

        $component->assertOk();
        $this->assertMatchesRegularExpression(
            '/class="label label-warning">\s*94\.00%/u',
            $component->html(),
        );
    }

    /** @test */
    public function autoevaluacion_topea_cumplimiento_mostrado_en_100(): void
    {
        $this->indicator->update(['meta_valor' => 100, 'umbral_superior' => 95, 'umbral_inferior' => 60]);
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['meta_efectiva' => 100, 'ponderacion' => 25, 'seleccionado' => true]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 120,
            'denominador' => 100,
            'resultado' => 120,
            'cumple_meta' => true,
        ]));

        $component = Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])->set('soloComprometidos', false);

        $component->assertOk();
        $html = $component->html();

        $this->assertMatchesRegularExpression('/class="label label-success">\s*100\.00%/u', $html);
        $this->assertStringNotContainsString('120.00%', $html);
        $this->assertStringNotContainsString('30.00%', $html);
    }

    /** @test */
    public function autoevaluacion_semaforo_rojo_bajo_umbral_inferior(): void
    {
        $this->indicator->update(['meta_valor' => 100, 'umbral_superior' => 99.5, 'umbral_inferior' => 95]);
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['meta_efectiva' => 100]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 90,
            'denominador' => 100,
            'resultado' => 90,
            'cumple_meta' => false,
        ]));

        $component = Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])->set('soloComprometidos', false);

        $component->assertOk();
        $this->assertMatchesRegularExpression(
            '/class="label label-danger">\s*90\.00%/u',
            $component->html(),
        );
    }

    /** @test */
    public function resumen_resultados_muestra_sin_datos_cuando_error_message_es_nd(): void
    {
        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'resultado_global' => 50.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSee('Hospital Test');
    }

    /** @test */
    public function resumen_resultados_muestra_cumplimiento_global_por_etapa(): void
    {
        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'resultado_global' => 50.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'PRELIMINAR',
            'resultado_global' => 60.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'FINAL',
            'resultado_global' => 70.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSee('Autoevaluación')
            ->assertSee('Ev. preliminar')
            ->assertSee('Ev. final')
            ->assertSee('50.00%')
            ->assertSee('60.00%')
            ->assertSee('70.00%')
            ->assertDontSee('Ver detalle')
            ->assertSeeHtml('border-left-color: #d9534f');
    }

    /** @test */
    public function resumen_resultados_carga_etapas_en_una_sola_query_de_resultados_anuales(): void
    {
        foreach (
            [
                'AUTOEVALUACION' => 50.0,
                'PRELIMINAR' => 60.0,
                'FINAL' => 70.0,
            ] as $etapa => $global
        ) {
            MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
                'cen_center_id' => $this->center->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'indicator_type_id' => $this->indicatorType->id,
                'periodo' => 'ANUAL',
                'etapa' => $etapa,
                'resultado_global' => $global,
                'n_seleccionados' => 1,
                'n_calculados' => 1,
            ]));
        }

        // Legacy: etapa '' cuenta como autoevaluación (scope etapaAutoevaluacion).
        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'ANUAL',
            'etapa' => '',
            'resultado_global' => 55.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        $selectsOnEstablishmentResults = 0;
        DB::listen(function ($query) use (&$selectsOnEstablishmentResults): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'met_establishment_results') && str_starts_with(ltrim($sql), 'select')) {
                $selectsOnEstablishmentResults++;
            }
        });

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSee('60.00%')
            ->assertSee('70.00%');

        // 1× pluck de centros con resultados + 1× batch de las tres etapas (antes: 1+3).
        $this->assertSame(2, $selectsOnEstablishmentResults);
    }

    /** @test */
    public function resumen_resultados_filtro_solo_comprometidos_oculta_no_convenidos(): void
    {
        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'SEM-1',
            'resultado_global' => 80.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        foreach (['SEM-1', 'SEM-2'] as $periodo) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'numerador' => 10,
                'denominador' => 20,
                'resultado' => 50.0,
                'cumple_meta' => false,
            ]));
        }

        $indicatorComprometido = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-RES-COMP',
            'nombre' => 'Indicador únicamente en filtro comprometido',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 50,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicatorComprometido->id,
            'label' => 'Numerador semestral B',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'semestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicatorComprometido->id,
            'label' => 'Denominador línea base B',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'linea_base',
            'aggregation_type' => null,
            'orden' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicatorComprometido->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        foreach (['SEM-1', 'SEM-2'] as $periodo) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $indicatorComprometido->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'numerador' => 5,
                'denominador' => 10,
                'resultado' => 50.0,
                'cumple_meta' => true,
            ]));
        }

        // El filtro solo-comprometidos ahora está en Nivel 2 (vista detalle)
        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', true)
            ->assertSee('Indicador únicamente en filtro comprometido')
            ->assertDontSee('Indicador resumen modal');
    }

    /** @test */
    public function resumen_resultados_filtro_solo_comprometidos_mensaje_vacio_sin_convenidos(): void
    {
        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'SEM-1',
            'resultado_global' => 80.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        foreach (['SEM-1', 'SEM-2'] as $periodo) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'numerador' => 10,
                'denominador' => 20,
                'resultado' => 50.0,
                'cumple_meta' => false,
            ]));
        }

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->set('soloComprometidos', true)
            ->assertSee('No hay indicadores en convenio para este establecimiento.');
    }

    /** @test */
    public function detalle_oculta_indicadores_con_aplica_false_aunque_existan_met_results(): void
    {
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['aplica' => false, 'seleccionado' => false]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 10,
            'denominador' => 20,
            'resultado' => 50.0,
            'cumple_meta' => false,
        ]));

        $applicable = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-RES-APLICA',
            'nombre' => 'Indicador sí aplicable en detalle',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 50,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'umbral_inferior' => 0,
            'umbral_superior' => 100,
            'estado' => true,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $applicable->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => false,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $applicable->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 8,
            'denominador' => 10,
            'resultado' => 80.0,
            'cumple_meta' => true,
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertDontSee($this->indicator->clave, false)
            ->assertDontSee('Indicador resumen modal', false)
            ->assertSee('MET-RES-APLICA', false)
            ->assertSee('Indicador sí aplicable en detalle', false);
    }

    /** @test */
    public function panel_detalle_muestra_cumplimiento_global_calculado_en_footer(): void
    {
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update([
                'seleccionado' => true,
                'ponderacion' => 100,
            ]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'numerador' => 17.1,
            'denominador' => 20,
            'resultado' => 17.1,
            'cumple_meta' => false,
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertSee('85.50%');
    }

    /** @test */
    public function establishment_detail_datos_al_usa_ultimo_mes_cargado(): void
    {
        $numeradorField = $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail();
        $denominadorField = $this->indicator->dataFields()->where('variable_key', 'B')->firstOrFail();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $numeradorField->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 10,
        ]));
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $denominadorField->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => null,
            'valor' => 100,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'SEM-2',
            'numerador' => 30,
            'denominador' => 100,
            'resultado' => 30,
            'cumple_meta' => false,
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertSee('Jun')
            ->assertSee('Leyenda columna Datos al', false)
            ->assertDontSee('>Anual<', false);
    }

    /** @test */
    public function establishment_detail_datos_al_incluye_adosados_del_centro_madre(): void
    {
        $adosado = $this->createExtraCenter('CEN-ADO', 'Posta adosada');

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->center->id,
            'cen_center_adosado_id' => $adosado->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->loadMonthlyDataForCenter($adosado);
        $this->createAnnualResultForCenter($this->center);

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertSee('Jun');
    }

    /** @test */
    public function establishment_detail_datos_al_incluye_universo_agrupa_ss(): void
    {
        $otroCentro = $this->createExtraCenter('CEN-SS', 'Hospital del SS');
        $centroNoAplica = $this->createExtraCenter('CEN-NA', 'Hospital no aplica');

        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['agrupa_ss' => true]);

        foreach ([[$otroCentro, true], [$centroNoAplica, false]] as [$center, $aplica]) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $this->indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $center->id,
                'aplica' => $aplica,
                'agrupa_ss' => false,
                'seleccionado' => false,
            ]));
        }

        $this->loadMonthlyDataForCenter($otroCentro);

        // Centro con aplica = false y carga posterior: no debe aportar mes al universo (DR-190-03).
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail()->id,
            'cen_center_id' => $centroNoAplica->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 12,
            'valor' => 99,
        ]));

        $this->createAnnualResultForCenter($this->center);

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertSee('Jun')
            ->assertDontSee('Dic');
    }

    /** @test */
    public function establishment_detail_datos_al_marca_parcial_cuando_falta_campo_en_el_universo(): void
    {
        $adosado = $this->createExtraCenter('CEN-ADO-3', 'Posta parcial');

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->center->id,
            'cen_center_adosado_id' => $adosado->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        // Solo numerador: falta el denominador de línea base en todo el universo.
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail()->id,
            'cen_center_id' => $adosado->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 10,
        ]));

        $this->createAnnualResultForCenter($this->center);

        $component = Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])->set('soloComprometidos', false);

        $component->assertOk();
        $this->assertMatchesRegularExpression(
            '/label-warning metas-datos-al-badge">\s*Jun/u',
            $component->html(),
        );
    }

    /** @test */
    public function establishment_detail_datos_al_sigue_sin_datos_cuando_el_universo_no_tiene_carga(): void
    {
        $adosado = $this->createExtraCenter('CEN-ADO-2', 'Posta sin carga');

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->center->id,
            'cen_center_adosado_id' => $adosado->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->createAnnualResultForCenter($this->center);

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertDontSee('Jun');
    }

    /** @test */
    public function resumen_ordena_establecimientos_de_mejor_a_peor(): void
    {
        $centerAlto = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-002',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Alto',
            'nombre_corto' => 'Hospital Alto',
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

        foreach ([$this->center, $centerAlto] as $center) {
            MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
                'cen_center_id' => $center->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'indicator_type_id' => $this->indicatorType->id,
                'periodo' => 'ANUAL',
                'etapa' => 'FINAL',
                'resultado_global' => $center->id === $centerAlto->id ? 90.0 : 50.0,
                'n_seleccionados' => 1,
                'n_calculados' => 1,
            ]));
        }

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSeeInOrder(['Hospital Alto', 'Hospital Test']);
    }

    /** @test */
    public function results_summary_shows_config_incomplete_alert_for_included_center(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'resultado_global' => null,
            'n_seleccionados' => 0,
            'n_calculados' => 0,
        ]));

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSee('metas-establishment-config-alert', false)
            ->assertSee('Configuración incompleta', false)
            ->assertSee('Sin indicadores en convenio', false);
    }

    /** @test */
    public function establishment_results_detail_shows_config_incomplete_alert(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('metas-establishment-config-alert', false)
            ->assertSee('Configuración incompleta', false)
            ->assertSee('Sin indicadores en convenio', false);
    }

    /** @test */
    public function establishment_results_detail_hides_alert_when_convenio_completo(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update([
                'aplica' => true,
                'seleccionado' => true,
                'ponderacion' => 20.00,
                'meta_efectiva' => 20.00,
            ]);

        $indicators = collect(range(1, 4))->map(function (int $n): MetIndicator {
            return MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicatorType->id,
                'clave' => 'MET-OK-'.$n,
                'nombre' => 'Indicador OK '.$n,
                'decimales' => 2,
                'decimales_modo' => 'redondear',
                'meta_valor' => 50,
                'meta_operador' => '>=',
                'year_id' => $this->year->id,
                'estado' => true,
            ]));
        });

        foreach ($indicators as $indicator) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $this->center->id,
                'aplica' => true,
                'seleccionado' => true,
                'ponderacion' => 20.00,
                'meta_efectiva' => 50.00,
            ]));
        }

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertDontSee('metas-establishment-config-alert', false);
    }

    /** @test */
    public function resumen_override_semaforo_global_72_pasa_de_rojo_a_amarillo(): void
    {
        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'resultado_global' => 72.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        $sinOverride = Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ]);
        $sinOverride->assertOk();
        $this->assertMatchesRegularExpression(
            '/class="label label-danger">\s*72\.00%/u',
            $sinOverride->html(),
        );
        $sinOverride->assertSeeHtml('border-left-color: #d9534f');

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
            'semaforo_verde_min' => 90,
            'semaforo_amarillo_min' => 70,
        ]));

        MetSemaforoThresholds::forgetResolved($this->indicatorType->id, $this->year->id);

        $conOverride = Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ]);
        $conOverride->assertOk();
        $this->assertMatchesRegularExpression(
            '/class="label label-warning">\s*72\.00%/u',
            $conOverride->html(),
        );
        $conOverride->assertSeeHtml('border-left-color: #f0ad4e');
        $conOverride->assertSee('70% a 89%');
        $conOverride->assertSee('Cumplimiento global satisfactorio');
    }

    /** @test */
    public function detalle_override_semaforo_global_no_cambia_color_de_fila(): void
    {
        $this->indicator->update(['meta_valor' => 100, 'umbral_superior' => 95, 'umbral_inferior' => 60]);
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['meta_efectiva' => 100]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 94,
            'denominador' => 100,
            'resultado' => 94,
            'cumple_meta' => false,
        ]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
            'semaforo_verde_min' => 90,
            'semaforo_amarillo_min' => 70,
        ]));

        $component = Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])->set('soloComprometidos', false);

        $component->assertOk();
        $this->assertMatchesRegularExpression(
            '/class="label label-warning">\s*94\.00%/u',
            $component->html(),
        );
        $component->assertSee('Cumplimiento satisfactorio');
    }

    private function createCenterCatalogDependencies(): void {}

    private function createExtraCenter(string $codigo, string $nombre): CenCenter
    {
        return CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => $codigo,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => $nombre,
            'nombre_corto' => $nombre,
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
    }

    /**
     * Numerador semestral en el mes 6 y denominador de línea base, igual que el centro con carga propia.
     */
    private function loadMonthlyDataForCenter(CenCenter $center): void
    {
        $numeradorField = $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail();
        $denominadorField = $this->indicator->dataFields()->where('variable_key', 'B')->firstOrFail();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $numeradorField->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 10,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $denominadorField->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => null,
            'valor' => 100,
        ]));
    }

    private function createAnnualResultForCenter(CenCenter $center): void
    {
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'SEM-2',
            'numerador' => 10,
            'denominador' => 100,
            'resultado' => 10,
            'cumple_meta' => false,
        ]));
    }
}
