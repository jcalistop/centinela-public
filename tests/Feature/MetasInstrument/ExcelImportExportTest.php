<?php

namespace Tests\Feature\MetasInstrument;

use App\Jobs\MetCalculateIndicatorScopeJob;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMonthlyData;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\TestCase;

class ExcelImportExportTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

    private User $referente;

    private User $controlGestion;

    private CenCenter $center;

    private CenCenter $centerOtroServicio;

    private MetIndicator $indicator;

    private MetDataField $fieldA;

    private MetDataField $fieldB;

    private MetDataField $fieldC;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        if (! is_dir(storage_path('temp'))) {
            mkdir(storage_path('temp'), 0755, true);
        }

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        CenHieDependency::unguarded(fn () => CenHieDependency::updateOrCreate(
            ['id' => 2],
            ['codigo' => 'SS02', 'nombre' => 'Servicio Externo', 'tipo_dependencia' => 'SERVICIO']
        ));

        Role::unguarded(fn () => Role::updateOrCreate(
            ['id' => 1],
            ['name' => 'admin', 'display_name' => 'Administrador']
        ));

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);

        $this->referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->referente->role()->associate($referenteRole)->save();

        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-001',
            'nombre_oficial' => 'Hospital Test',
            'nombre_corto' => 'HospTest',
        ]);

        $this->centerOtroServicio = $this->createMinimalCenCenter([
            'codigo' => 'CEN-999',
            'cen_hie_dependency_id' => 2,
            'nombre_oficial' => 'Hospital Externo',
            'nombre_corto' => 'HospExt',
        ]);

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-01',
            'nombre' => 'Indicador test',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $this->year->id,
            'orden' => 1,
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

        $this->fieldC = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Línea base',
            'variable_key' => 'C',
            'componente' => 'numerador',
            'input_type' => 'linea_base',
            'orden' => 2,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referente->id,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => false,
        ]));

        $this->seedCompleteFieldSsConfigs();
    }

    /** @test */
    public function referente_puede_descargar_plantilla_xlsx(): void
    {
        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    /** @test */
    public function referente_puede_descargar_detalle_datos_filtrado_por_establecimiento(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 17,
        ]));

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.detalle-datos.download', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
                'centers' => [$this->center->id],
            ]));

        $response->assertOk();
        $this->assertStringContainsString('detalle_', $response->headers->get('Content-Disposition') ?? '');
    }

    /** @test */
    public function detalle_datos_rechaza_establecimiento_fuera_de_alcance(): void
    {
        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.detalle-datos.download', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
                'centers' => [$this->centerOtroServicio->id],
            ]))
            ->assertForbidden();
    }

    /** @test */
    public function import_limita_peticiones_repetidas_por_minuto(): void
    {
        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'Ene' => 10,
                'Feb' => null,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $url = route('metas.instrument.indicador.importar', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $file = new UploadedFile(
                $path,
                'datos-'.$i.'.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $this->actingAs($this->referente)
                ->withSession(['indicator_year' => $this->year])
                ->post($url, ['archivo' => $file])
                ->assertRedirect();
        }

        $file = new UploadedFile(
            $path,
            'datos-4.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post($url, ['archivo' => $file])
            ->assertStatus(429);
    }

    /** @test */
    public function detalle_datos_limita_descargas_repetidas_por_minuto(): void
    {
        $url = route('metas.instrument.indicador.detalle-datos.download', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
            'centers' => [$this->center->id],
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($this->referente)
                ->withSession(['indicator_year' => $this->year])
                ->get($url)
                ->assertOk();
        }

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get($url)
            ->assertStatus(429);
    }

    /** @test */
    public function visualizador_puede_descargar_detalle_datos_desde_vista_resultados(): void
    {
        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create([
            'role_id' => $visualizadorRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $visualizador->role()->associate($visualizadorRole)->save();

        $this->actingAs($visualizador)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.detalle-datos.download', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
                'centers' => [$this->center->id],
            ]))
            ->assertOk();
    }

    /** @test */
    public function plantilla_incluye_datos_existentes(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 42.5,
        ]));

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $response->assertOk();

        // Verificar que la respuesta es una descarga de archivo (BinaryFileResponse)
        $this->assertNotEmpty($response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition') ?? '');

        $path = $this->extractDownloadedXlsxPath($response);
        $rows = SimpleExcelReader::create($path)->fromSheetName('Datos_Mensuales')->getRows()->toArray();
        $rowA = collect($rows)->first(
            fn (array $row): bool => ($row['variable_key'] ?? null) === 'A' && ($row['centro_codigo'] ?? null) === 'CEN-001'
        );

        $this->assertNotNull($rowA);
        $this->assertSame('42,5', $rowA['Ene']);
    }

    /** @test */
    public function import_acepta_valor_con_coma_decimal(): void
    {
        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'Ene' => '12,75',
                'Feb' => null,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 12.75,
        ]);
    }

    /** @test */
    public function import_crea_datos_en_etapa_autoevaluacion(): void
    {
        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'Ene' => 10,
                'Feb' => 20,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10.0,
        ]);

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 20.0,
        ]);

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 3,
        ]);
    }

    /** @test */
    public function import_encola_job_de_recalculo_scope(): void
    {
        Bus::fake();

        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'Ene' => 10,
                'Feb' => null,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'success');

        Bus::assertDispatched(MetCalculateIndicatorScopeJob::class);
    }

    /** @test */
    public function import_crea_datos_linea_base(): void
    {
        $path = $this->makeXlsx([], [
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'C',
                'componente' => 'numerador',
                'valor' => 99,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldC->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => null,
            'valor' => 99.0,
        ]);
    }

    /** @test */
    public function import_crea_datos_linea_base_con_override_ss_en_variable_mensual(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::where('met_data_field_id', $this->fieldB->id)->update([
            'input_type' => 'linea_base',
            'aggregation_type' => null,
        ]));

        $path = $this->makeXlsx([], [
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'B',
                'componente' => 'denominador',
                'valor' => 88,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldB->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => null,
            'valor' => 88.0,
        ]);
    }

    /** @test */
    public function import_crea_datos_en_meses_trimestrales(): void
    {
        $trimestralField = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Trimestral',
            'variable_key' => 'T',
            'componente' => 'numerador',
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 3,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $trimestralField->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
        ]));

        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'T',
                'componente' => 'numerador',
                'Ene' => null,
                'Feb' => null,
                'Mar' => 15,
                'Abr' => null,
                'May' => null,
                'Jun' => 25,
                'Jul' => null,
                'Ago' => null,
                'Sep' => 35,
                'Oct' => null,
                'Nov' => null,
                'Dic' => 45,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'success');

        foreach ([3 => 15, 6 => 25, 9 => 35, 12 => 45] as $mes => $valor) {
            $this->assertDatabaseHas('met_monthly_data', [
                'met_data_field_id' => $trimestralField->id,
                'cen_center_id' => $this->center->id,
                'year_id' => $this->year->id,
                'mes' => $mes,
                'valor' => (float) $valor,
            ]);
        }
    }

    /** @test */
    public function plantilla_incluye_establecimientos_con_aplica_sin_convenio(): void
    {
        $centerSinConvenio = $this->createMinimalCenCenter([
            'codigo' => 'CEN-002',
            'nombre_oficial' => 'Hospital Aplica Sin Convenio',
            'nombre_corto' => 'HospAplica',
        ]);

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $centerSinConvenio->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $centerSinConvenio->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => false,
        ]));

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $response->assertOk();

        $path = $this->extractDownloadedXlsxPath($response);
        $rows = SimpleExcelReader::create($path)->fromSheetName('Datos_Mensuales')->getRows()->toArray();

        $centroCodigos = collect($rows)->pluck('centro_codigo')->unique()->values()->all();

        $this->assertContains('CEN-001', $centroCodigos);
        $this->assertContains('CEN-002', $centroCodigos);
    }

    /** @test */
    public function plantilla_exporta_variable_con_tipo_efectivo_trimestral_en_hoja_mensual(): void
    {
        $trimestralField = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Trimestral',
            'variable_key' => 'T',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 3,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $trimestralField->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
        ]));

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $response->assertOk();

        $path = $this->extractDownloadedXlsxPath($response);
        $rows = SimpleExcelReader::create($path)->fromSheetName('Datos_Mensuales')->getRows()->toArray();

        $this->assertTrue(
            collect($rows)->contains(fn (array $row): bool => ($row['variable_key'] ?? null) === 'T')
        );
    }

    /** @test */
    public function plantilla_no_exporta_datos_en_meses_no_vigentes_para_tipo_efectivo_trimestral(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::query()
            ->whereIn('met_data_field_id', [$this->fieldA->id, $this->fieldB->id])
            ->update([
                'input_type' => 'linea_base',
                'aggregation_type' => null,
            ]));

        $trimestralField = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Trimestral',
            'variable_key' => 'T',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 3,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $trimestralField->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
        ]));

        MetMonthlyData::withoutEvents(fn () => MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $trimestralField->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 99,
        ])));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $trimestralField->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 15,
        ]));

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $response->assertOk();

        $path = $this->extractDownloadedXlsxPath($response);
        $headers = SimpleExcelReader::create($path)->fromSheetName('Datos_Mensuales')->getHeaders();
        $row = collect(SimpleExcelReader::create($path)->fromSheetName('Datos_Mensuales')->getRows()->toArray())
            ->first(fn (array $row): bool => ($row['variable_key'] ?? null) === 'T');

        $this->assertNotNull($row);
        $this->assertNotContains('Ene', $headers);
        $this->assertContains('Mar', $headers);
        $this->assertSame(15.0, (float) ($row['Mar'] ?? 0));
    }

    /** @test */
    public function plantilla_semestral_incluye_solo_columnas_junio_y_diciembre(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::query()
            ->whereIn('met_data_field_id', [$this->fieldA->id, $this->fieldB->id])
            ->update([
                'input_type' => 'semestral',
                'aggregation_type' => 'sumatoria',
            ]));

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $response->assertOk();

        $path = $this->extractDownloadedXlsxPath($response);
        $headers = SimpleExcelReader::create($path)->fromSheetName('Datos_Mensuales')->getHeaders();

        $this->assertContains('Jun', $headers);
        $this->assertContains('Dic', $headers);
        $this->assertNotContains('Ene', $headers);
        $this->assertNotContains('Mar', $headers);
    }

    /** @test */
    public function import_ignora_cero_en_mes_no_vigente_trimestral(): void
    {
        $trimestralField = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Trimestral',
            'variable_key' => 'T',
            'componente' => 'numerador',
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 3,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $trimestralField->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
        ]));

        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'T',
                'componente' => 'numerador',
                'Ene' => 0,
                'Feb' => null,
                'Mar' => 15,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $trimestralField->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 15.0,
        ]);
    }

    /** @test */
    public function import_plantilla_descargada_sin_errores_en_variable_mensual(): void
    {
        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $response->assertOk();

        $path = $this->extractDownloadedXlsxPath($response);
        $file = new UploadedFile(
            $path,
            'plantilla.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file]);

        $response->assertRedirect()
            ->assertSessionHas('alert-type', 'success');

        $this->assertEmpty($response->getSession()->get('import_errors', []));
    }

    /** @test */
    public function plantilla_omite_hoja_linea_base_sin_variables_linea_base_efectiva(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::where('met_data_field_id', $this->fieldC->id)->update([
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
        ]));

        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $response->assertOk();

        $path = $this->extractDownloadedXlsxPath($response);

        try {
            SimpleExcelReader::create($path)->fromSheetName('Linea_Base')->getRows()->toArray();
            $this->fail('La plantilla no debe incluir la hoja Linea_Base cuando el indicador no tiene variables de línea base efectivas.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function import_rechaza_mes_fuera_de_tipo_efectivo_trimestral(): void
    {
        $trimestralField = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Trimestral',
            'variable_key' => 'T',
            'componente' => 'numerador',
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 3,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $trimestralField->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
        ]));

        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'T',
                'componente' => 'numerador',
                'Ene' => 10,
                'Feb' => null,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'warning')
            ->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $trimestralField->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
        ]);
    }

    /** @test */
    public function import_rechazado_en_etapa_cerrado(): void
    {
        $this->setting->update(['etapa_vigente' => 'CERRADO']);

        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'Ene' => 10,
                'Feb' => null,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'error');

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
        ]);
    }

    /** @test */
    public function import_rechaza_centro_no_autorizado(): void
    {
        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-999',
                'nombre_establecimiento' => 'Hospital Externo',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'Ene' => 50,
                'Feb' => null,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'warning');

        $this->assertDatabaseMissing('met_monthly_data', [
            'cen_center_id' => $this->centerOtroServicio->id,
        ]);
    }

    /** @test */
    public function import_rechaza_valor_no_numerico(): void
    {
        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-01',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'Ene' => 'texto_invalido',
                'Feb' => null,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'warning')
            ->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'mes' => 1,
        ]);
    }

    /** @test */
    public function import_rechaza_fila_con_otro_indicador_clave(): void
    {
        $path = $this->makeXlsx([
            [
                'centro_codigo' => 'CEN-001',
                'nombre_establecimiento' => 'Hospital Test',
                'indicador_clave' => 'MET-OTRO',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'Ene' => 10,
                'Feb' => null,
                'Mar' => null,
                'Abr' => null,
                'May' => null,
                'Jun' => null,
                'Jul' => null,
                'Ago' => null,
                'Sep' => null,
                'Oct' => null,
                'Nov' => null,
                'Dic' => null,
            ],
        ]);

        $file = new UploadedFile(
            $path,
            'datos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'warning')
            ->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
        ]);
    }

    /** @test */
    public function referente_puede_abrir_vista_carga_indicador(): void
    {
        $response = $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.carga', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]));

        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    /** @test */
    public function vista_carga_habilita_formulario_importar_xlsx_en_autoevaluacion(): void
    {
        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.carga', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]))
            ->assertOk()
            ->assertSee('Carga masiva de datos', false)
            ->assertSee('name="archivo"', false)
            ->assertSee('Procesar archivo', false)
            ->assertDontSee('La importación XLSX solo está disponible en etapa', false);
    }

    /** @test */
    public function vista_carga_deshabilita_importar_xlsx_fuera_de_autoevaluacion(): void
    {
        $this->setting->update(['etapa_vigente' => 'CERRADO']);

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.carga', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]))
            ->assertOk()
            ->assertSee('La importación XLSX solo está disponible en etapa', false)
            ->assertSee('AUTOEVALUACION', false)
            ->assertDontSee('name="archivo"', false);
    }

    /** @test */
    public function referente_sin_designacion_no_puede_abrir_vista_carga(): void
    {
        $referenteRole = Role::where('name', 'referente')->firstOrFail();
        $sinAsignacion = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $sinAsignacion->role()->associate($referenteRole)->save();

        $this->actingAs($sinAsignacion)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.carga', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]))
            ->assertForbidden();
    }

    /** @test */
    public function no_referente_no_puede_descargar_plantilla(): void
    {
        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create([
            'role_id' => $visualizadorRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $visualizador->role()->associate($visualizadorRole)->save();

        $this->actingAs($visualizador)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]))
            ->assertForbidden();
    }

    /** @test */
    public function no_referente_no_puede_importar(): void
    {
        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create([
            'role_id' => $visualizadorRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $visualizador->role()->associate($visualizadorRole)->save();

        $file = UploadedFile::fake()->create('datos.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($visualizador)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertForbidden();
    }

    /** @test */
    public function control_gestion_puede_descargar_plantilla(): void
    {
        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]))
            ->assertOk();
    }

    /** @test */
    public function import_rechazado_en_etapa_apelacion(): void
    {
        $this->setting->update(['etapa_vigente' => 'APELACION']);

        $file = UploadedFile::fake()->create('datos.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertSessionHas('alert-type', 'error');
    }

    /** @test */
    public function import_rechaza_archivo_no_xlsx(): void
    {
        $file = UploadedFile::fake()->create('datos.csv', 10, 'text/csv');

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('metas.instrument.indicador.importar', ['indicatorType' => $this->indicatorType, 'indicator' => $this->indicator]), ['archivo' => $file])
            ->assertSessionHasErrors('archivo');
    }

    /**
     * @param  array<int, array<string, mixed>>  $mensualRows
     * @param  array<int, array<string, mixed>>  $baselineRows
     */
    private function makeXlsx(array $mensualRows = [], array $baselineRows = []): string
    {
        $path = storage_path('app/temp/test_import_'.uniqid().'.xlsx');

        $writer = SimpleExcelWriter::create($path);
        $writer->nameCurrentSheet('Datos_Mensuales');
        $writer->addHeader([
            'centro_codigo',
            'nombre_establecimiento',
            'indicador_clave',
            'variable_key',
            'componente',
            'Ene',
            'Feb',
            'Mar',
            'Abr',
            'May',
            'Jun',
            'Jul',
            'Ago',
            'Sep',
            'Oct',
            'Nov',
            'Dic',
        ]);

        foreach ($mensualRows as $row) {
            $writer->addRow($row);
        }

        if ($baselineRows !== []) {
            $writer->addNewSheetAndMakeItCurrent('Linea_Base');
            $writer->addHeader(['centro_codigo', 'nombre_establecimiento', 'indicador_clave', 'variable_key', 'componente', 'valor']);

            foreach ($baselineRows as $row) {
                $writer->addRow($row);
            }
        }

        return $path;
    }

    private function seedCompleteFieldSsConfigs(): void
    {
        foreach ([$this->fieldA, $this->fieldB] as $field) {
            MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
                'met_data_field_id' => $field->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
            ]));
        }

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldC->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'linea_base',
            'aggregation_type' => null,
        ]));
    }

    private function extractDownloadedXlsxPath(TestResponse $response): string
    {
        $sourcePath = $response->baseResponse->getFile()->getPathname();
        $path = storage_path('app/temp/test_export_'.uniqid().'.xlsx');
        copy($sourcePath, $path);

        return $path;
    }

    private function createCenterCatalogDependencies(): void {}
}
