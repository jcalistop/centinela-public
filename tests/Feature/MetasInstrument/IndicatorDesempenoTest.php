<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\IndicatorDesempenoComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndicatorDesempenoTest extends TestCase
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
            'nombre' => 'Indicador desempeño vista',
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
    public function desempeno_route_redirects_to_show_tab(): void
    {
        $this->get(route('metas.instrument.indicators.desempeno', [$this->indicatorType, $this->indicator]))
            ->assertRedirect(route('metas.instrument.indicators.show', [$this->indicatorType, $this->indicator]).'#tab-desempeno');
    }

    /** @test */
    public function show_page_includes_desempeno_tab_for_authorized_roles(): void
    {
        $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $this->indicator]))
            ->assertOk()
            ->assertSee('Ficha del indicador', false)
            ->assertSee('Desempeño', false)
            ->assertSee('id="tab-desempeno"', false);
    }

    /** @test */
    public function desempeno_vista_muestra_promedio_cumplimiento_en_fila_final(): void
    {
        $this->indicator->update(['meta_valor' => 100]);

        $centerDos = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-002',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'CESFAM Test',
            'nombre_corto' => 'CESFAM Test',
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

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $centerDos->id,
            'aplica' => true,
            'seleccionado' => false,
        ]));

        $numeradorField = $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail();
        $denominadorField = $this->indicator->dataFields()->where('variable_key', 'B')->firstOrFail();

        foreach ([$this->center, $centerDos] as $center) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $numeradorField->id,
                'cen_center_id' => $center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 6,
                'valor' => $center->id === $this->center->id ? 80 : 100,
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

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'numerador' => 80,
            'denominador' => 100,
            'resultado' => 80,
            'cumple_meta' => true,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $centerDos->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'numerador' => 100,
            'denominador' => 100,
            'resultado' => 100,
            'cumple_meta' => true,
        ]));

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('promedioCumplimiento', 90.0)
            ->assertSee('Promedio cumplimiento')
            ->assertSee('90.00%')
            ->assertSeeHtml('colspan="6"')
            ->assertSee('Fórmula de cálculo', false)
            ->assertDontSeeHtml('>Numerador</')
            ->assertDontSeeHtml('>Denominador</')
            ->assertSee('80 / 100', false)
            ->assertSee('100 / 100', false);
    }

    /** @test */
    public function desempeno_vista_semaforo_usa_umbral_superior_del_indicador(): void
    {
        $this->indicator->update(['meta_valor' => 100, 'umbral_superior' => 95]);

        $numeradorField = $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail();
        $denominadorField = $this->indicator->dataFields()->where('variable_key', 'B')->firstOrFail();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $numeradorField->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 94,
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
            'periodo' => 'ANUAL',
            'numerador' => 94,
            'denominador' => 100,
            'resultado' => 94,
            'cumple_meta' => false,
        ]));

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('umbralSuperior', 95.0)
            ->assertSeeHtml('<span class="label label-warning">')
            ->assertSee('94.00%');
    }

    /** @test */
    public function desempeno_vista_topea_cumplimiento_en_100_con_semaforo_verde(): void
    {
        $this->indicator->update(['meta_valor' => 100, 'umbral_superior' => 95, 'umbral_inferior' => 60]);

        $numeradorField = $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail();
        $denominadorField = $this->indicator->dataFields()->where('variable_key', 'B')->firstOrFail();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $numeradorField->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 120,
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
            'periodo' => 'ANUAL',
            'numerador' => 120,
            'denominador' => 100,
            'resultado' => 120,
            'cumple_meta' => true,
        ]));

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('umbralInferior', 60.0)
            ->assertSeeHtml('<span class="label label-success">')
            ->assertSee('100.00%')
            ->assertDontSee('120.00%');
    }

    /** @test */
    public function desempeno_vista_semaforo_rojo_bajo_umbral_inferior(): void
    {
        $this->indicator->update(['meta_valor' => 100, 'umbral_superior' => 99.5, 'umbral_inferior' => 95]);

        $numeradorField = $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail();
        $denominadorField = $this->indicator->dataFields()->where('variable_key', 'B')->firstOrFail();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $numeradorField->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 90,
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
            'periodo' => 'ANUAL',
            'numerador' => 90,
            'denominador' => 100,
            'resultado' => 90,
            'cumple_meta' => false,
        ]));

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSeeHtml('<span class="label label-danger">')
            ->assertSee('90.00%');
    }

    /** @test */
    public function desempeno_vista_no_acumula_denominador_linea_base(): void
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

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('desempenoData', function (array $rows): bool {
                $row = collect($rows)->firstWhere('centro_codigo', 'CEN-001');

                return $row !== null
                    && (float) $row['numerador'] === 246.0
                    && (float) $row['denominador'] === 250.0
                    && (float) $row['resultado'] === 98.4;
            });
    }

    /** @test */
    public function desempeno_vista_lista_establecimiento_aplica_sin_resultado_calculado(): void
    {
        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('desempenoData', function (array $rows): bool {
                $row = collect($rows)->firstWhere('centro_codigo', 'CEN-001');

                return $row !== null
                    && $row['resultado'] === null
                    && $row['convenido'] === false;
            });
    }

    /** @test */
    public function desempeno_vista_muestra_sin_datos_cuando_error_message_es_nd(): void
    {
        foreach (['SEM-1', 'SEM-2'] as $periodo) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'numerador' => null,
                'denominador' => null,
                'resultado' => null,
                'error_message' => 'N/D',
            ]));
        }

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSee('Sin datos');
    }

    /** @test */
    public function desempeno_vista_muestra_error_de_calculo(): void
    {
        foreach (['SEM-1', 'SEM-2'] as $periodo) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'numerador' => null,
                'denominador' => null,
                'resultado' => null,
                'error_message' => 'Fórmula inválida',
            ]));
        }

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('desempenoData', function (array $rows): bool {
                $row = collect($rows)->firstWhere('centro_codigo', 'CEN-001');

                return $row !== null
                    && $row['error'] === 'Fórmula inválida';
            });
    }

    /** @test */
    public function desempeno_datos_al_muestra_sin_datos_sin_carga_mensual(): void
    {
        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSee('Sin datos')
            ->assertDontSee('Anual')
            ->assertSee('Leyenda columna Datos al', false);
    }

    /** @test */
    public function desempeno_datos_al_muestra_ultimo_mes_cargado_por_establecimiento(): void
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
            'numerador' => 60,
            'denominador' => 100,
            'resultado' => 60,
            'cumple_meta' => true,
        ]));

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('desempenoData', function (array $rows): bool {
                $row = collect($rows)->firstWhere('centro_codigo', 'CEN-001');

                return $row !== null
                    && $row['ultimo_mes_cargado'] === 6
                    && $row['estado_carga'] === 'cargado';
            })
            ->assertSee('Jun');
    }

    /** @test */
    public function desempeno_datos_al_muestra_parcial_con_solo_numerador_en_ultimo_mes(): void
    {
        $numeradorField = $this->indicator->dataFields()->where('variable_key', 'A')->firstOrFail();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $numeradorField->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 12,
            'valor' => 123,
        ]));

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('desempenoData', function (array $rows): bool {
                $row = collect($rows)->firstWhere('centro_codigo', 'CEN-001');

                return $row !== null
                    && $row['ultimo_mes_cargado'] === 12
                    && $row['estado_carga'] === 'parcial';
            })
            ->assertSee('Dic')
            ->assertSee('Parcial');
    }

    /** @test */
    public function desempeno_muestra_boton_cargar_datos_para_rol_autorizado(): void
    {
        $cargaUrl = route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]);

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('canShowCargaButton', true)
            ->assertSee('Cargar datos')
            ->assertSeeHtml('href="'.$cargaUrl.'"')
            ->assertSee('Copiar imagen')
            ->assertSee('Imprimir');
    }

    /** @test */
    public function desempeno_oculta_boton_cargar_datos_para_visualizador(): void
    {
        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create([
            'role_id' => $visualizadorRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $visualizador->role()->associate($visualizadorRole)->save();

        $this->actingAs($visualizador)->withSession(['indicator_year' => $this->year]);

        $cargaUrl = route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]);

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('canShowCargaButton', false)
            ->assertDontSeeHtml('href="'.$cargaUrl.'"')
            ->assertSee('Copiar imagen')
            ->assertSee('Imprimir');
    }

    /** @test */
    public function desempeno_muestra_visualizar_datos_y_deja_la_descarga_en_el_modal(): void
    {
        $this->indicator->update(['umbral_inferior' => 60, 'umbral_superior' => 95]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
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

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSee('Visualizar datos')
            ->assertDontSee('Descargar datos')
            ->assertDontSeeHtml('href="'.$downloadUrl.'"')
            ->call('openDataModal', $this->center->id)
            ->assertDispatched('open-data-modal', function (string $eventName, array $payload) use ($downloadUrl): bool {
                return ($payload['clave'] ?? '') === 'MET-RES-01'
                    && ($payload['centerNombre'] ?? '') === 'Hospital Test'
                    && ($payload['centerCodigo'] ?? '') === 'CEN-001'
                    && ($payload['dataEntryUrl'] ?? null) === $downloadUrl
                    && (float) ($payload['umbralInferior'] ?? null) === 60.0
                    && (float) ($payload['umbralSuperior'] ?? null) === 95.0
                    && is_array($payload['periodos'] ?? null);
            });
    }

    /** @test */
    public function modal_partial_y_js_usan_columna_formula_de_calculo(): void
    {
        $blade = file_get_contents(resource_path('views/partials/metas/indicator-data-modal.blade.php'));
        $js = file_get_contents(public_path('js/metas-indicator-data-modal.js'));

        $this->assertStringContainsString('Fórmula de cálculo', $blade);
        $this->assertStringNotContainsString('>Numerador</th>', $blade);
        $this->assertStringNotContainsString('>Denominador</th>', $blade);

        $this->assertStringContainsString('F\\u00f3rmula de c\\u00e1lculo', $js);
        $this->assertStringContainsString('formulaQuotientHtml', $js);
        $this->assertStringNotContainsString('Numerador / denominador', $js);
        $this->assertStringNotContainsString("'>Numerador</th>'", $js);
    }

    private function createCenterCatalogDependencies(): void {}
}
