<?php

namespace Tests\Feature\Comges;

use App\Enums\Indicators\IndicatorActivityAction;
use App\Enums\Indicators\IndicatorModuleType;
use App\Jobs\Comges\ImportComgesEvaluationsJob;
use App\Mail\Comges\ComgesEvaluationsImportReportMail;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\ComgesResultsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\TestCase;

class ComgesMassResultsFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $controlGestion;

    private int $cenHieDependencyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cenHieDependencyId = $this->seedDependency();

        // Asegurar que COMGES indicator_type exista con estado=true
        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'COMGES',
                'nombre_indicador' => 'Compromisos de Gestión',
                'estado' => true,
                'orden' => 1,
            ]
        ));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();
    }

    public function test_download_redirects_with_warning_when_year_is_less_than_2025(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));

        $response = $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/comges/carga-masiva-resultados/plantilla?tipo_evaluacion=AUTOEVALUACION');

        $response->assertRedirect('/admin/comges/carga-masiva-resultados?tipo_evaluacion=AUTOEVALUACION');
        $response->assertSessionHas('alert-type', 'warning');
    }

    public function test_download_excludes_apelacion_actions_and_filters_evaluacion_local_for_preliminar(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));
        $this->seedComgesGraphForYear((int) $year->id);

        // PRELIMINAR should exclude evaluacion_local=1 and also any APELACION cutoff actions.
        $response = $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/comges/carga-masiva-resultados/plantilla?tipo_evaluacion=PRELIMINAR');

        $response->assertOk();

        $file = $response->baseResponse->getFile();
        $sheetXml = $this->xlsxSheetXmlFromPath($file->getPathname());

        $this->assertStringContainsString('<v>'.$this->actionEligiblePreliminarId.'</v>', $sheetXml);
        $this->assertStringNotContainsString('<v>'.$this->actionEvaluacionLocalTrueId.'</v>', $sheetXml);
        $this->assertStringNotContainsString('<v>'.$this->actionApelacionId.'</v>', $sheetXml);
    }

    public function test_import_job_forgets_global_results_cache_after_successful_rows(): void
    {
        Mail::fake();
        Storage::makeDirectory('temp');

        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));
        $this->seedComgesGraphForYear((int) $year->id);

        $cacheKey = ComgesResultsService::globalResultsCacheKey(
            $this->cenHieDependencyId,
            (int) $year->id,
        );
        Cache::put($cacheKey, ['stale' => true], 3600);
        $this->assertTrue(Cache::has($cacheKey));

        $storedPath = $this->writeImportXlsx([
            [
                'servicio_salud' => 'Servicio de Salud Test',
                'ano' => 2025,
                'tipo_evaluacion' => 'FINAL',
                'com_action_id' => $this->actionEligiblePreliminarId,
                'compromiso' => 'K1',
                'indicador' => 'I1',
                'corte' => 'CORTE I',
                'accion' => '1',
                'numerador' => 80,
                'denominador' => 100,
            ],
        ]);

        $job = new ImportComgesEvaluationsJob(
            storedPath: $storedPath,
            tipoEvaluacion: 'FINAL',
            userId: $this->controlGestion->id,
            cenHieDependencyId: $this->cenHieDependencyId,
            yearId: (int) $year->id,
        );

        $job->handle();

        $this->assertFalse(Cache::has($cacheKey));

        $this->assertDatabaseCount('indicator_activities', 1);
        $this->assertDatabaseHas('indicator_activities', [
            'indicator_type' => IndicatorModuleType::Comges->value,
            'indicator_id' => $this->seededComIndicatorId,
            'action' => IndicatorActivityAction::EvaluationLoaded->value,
            'user_id' => $this->controlGestion->id,
            'description' => 'Se registró carga masiva de evaluación FINAL (1 registro).',
        ]);
    }

    public function test_job_import_rejects_ineligible_action_and_reports_error_by_mail(): void
    {
        Mail::fake();
        Storage::makeDirectory('temp');

        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));
        $this->seedComgesGraphForYear((int) $year->id);

        $storedPath = $this->writeImportXlsx([
            [
                'servicio_salud' => 'Servicio de Salud Test',
                'ano' => 2025,
                'tipo_evaluacion' => 'PRELIMINAR',
                'com_action_id' => $this->actionEvaluacionLocalTrueId, // ineligible for PRELIMINAR
                'compromiso' => 'K1',
                'indicador' => 'I1',
                'corte' => 'CORTE I',
                'accion' => '1',
                'numerador' => 1,
                'denominador' => 1,
            ],
        ]);

        $job = new ImportComgesEvaluationsJob(
            storedPath: $storedPath,
            tipoEvaluacion: 'PRELIMINAR',
            userId: $this->controlGestion->id,
            cenHieDependencyId: $this->cenHieDependencyId,
            yearId: (int) $year->id,
        );

        $job->handle();

        $this->assertDatabaseMissing('com_evaluations', [
            'com_action_id' => $this->actionEvaluacionLocalTrueId,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
            'tipo_evaluacion' => 'PRELIMINAR',
        ]);

        Mail::assertSent(ComgesEvaluationsImportReportMail::class, function (ComgesEvaluationsImportReportMail $mail): bool {
            return $mail->processed === 1
                && $mail->successful === 0
                && count($mail->errors) === 1;
        });
    }

    // --- Test fixtures/helpers ---

    private int $actionEligiblePreliminarId;

    private int $actionEvaluacionLocalTrueId;

    private int $actionApelacionId;

    private int $seededComIndicatorId;

    private function seedDependency(): int
    {
        return (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedComgesGraphForYear(int $yearId): void
    {
        $suffix = bin2hex(random_bytes(4));

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-mass-results-test-'.$suffix,
            'nombre_indicador' => 'COMGES Test Type '.$suffix,
            'descripcion_indicador' => null,
            'icon_class' => null,
            'route' => 'compromisos-test-'.$suffix,
            'view' => null,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffCorteId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1, 2, 3]),
            'nombre_corte' => 'Corte I '.$suffix,
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffApelacionId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'APELACION',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([]),
            'nombre_corte' => 'Apelación '.$suffix,
            'orden' => 99,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'K1',
            'nombre' => 'Compromiso',
            'descripcion' => null,
            'orden' => 0,
            'year_id' => $yearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I1',
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 1,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seededComIndicatorId = $indicatorId;

        $cutoffEligibleId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffCorteId,
            'ponderacion_corte' => 1,
            'promedio_pais' => null,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffEvaluacionLocalTrueId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffCorteId,
            'ponderacion_corte' => 1,
            'promedio_pais' => null,
            'evaluacion_local' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffApelacionLinkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffApelacionId,
            'ponderacion_corte' => 1,
            'promedio_pais' => null,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actionEligiblePreliminarId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $cutoffEligibleId,
            'numero' => '1',
            'meta_texto' => null,
            'tipo_medio' => null,
            'medio_verificacion' => null,
            'formula' => null,
            'fuente' => null,
            'observaciones' => null,
            'ponderacion_accion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actionEvaluacionLocalTrueId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $cutoffEvaluacionLocalTrueId,
            'numero' => '2',
            'meta_texto' => null,
            'tipo_medio' => null,
            'medio_verificacion' => null,
            'formula' => null,
            'fuente' => null,
            'observaciones' => null,
            'ponderacion_accion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actionApelacionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $cutoffApelacionLinkId,
            'numero' => '3',
            'meta_texto' => null,
            'tipo_medio' => null,
            'medio_verificacion' => null,
            'formula' => null,
            'fuente' => null,
            'observaciones' => null,
            'ponderacion_accion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sensitivity selection needed for import calculations on eligible actions.
        $this->seedSensitivityForAction($this->actionEligiblePreliminarId);
        $this->seedSensitivityForAction($this->actionEvaluacionLocalTrueId);
    }

    private function seedSensitivityForAction(int $comActionId): void
    {
        $tableId = (int) DB::table('com_sensitivity_tables')->insertGetId([
            'com_action_id' => $comActionId,
            'meta_numero' => 100,
            'valores' => json_encode([[
                'valor_minimo' => 0,
                'operador_minimo' => '>=',
                'valor_maximo' => 101,
                'operador_maximo' => '<',
                'cumplimiento' => 4,
            ]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_sensitivity_dependencies')->insert([
            'com_sensitivity_table_id' => $tableId,
            'com_action_id' => $comActionId,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function xlsxSheetXmlFromPath(string $xlsxPath): string
    {
        $zip = new \ZipArchive;
        $opened = $zip->open($xlsxPath);
        if ($opened !== true) {
            $this->fail('No se pudo abrir XLSX (ZipArchive)');
        }

        $sheetXml = null;
        for ($i = 1; $i <= 5; $i++) {
            $candidate = $zip->getFromName('xl/worksheets/sheet'.$i.'.xml');
            if (is_string($candidate)) {
                $sheetXml = $candidate;
                break;
            }
        }
        $zip->close();

        if (! is_string($sheetXml)) {
            $this->fail('No se pudo leer xl/worksheets/sheet*.xml');
        }

        return $sheetXml;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeImportXlsx(array $rows): string
    {
        $filename = 'temp/comges_import_test_'.bin2hex(random_bytes(4)).'.xlsx';
        $fullPath = storage_path('app/'.$filename);

        $writer = SimpleExcelWriter::create($fullPath);
        $writer->nameCurrentSheet('Resultados');
        foreach ($rows as $row) {
            $writer->addRow($row);
        }

        return $filename;
    }

    public function test_carga_masiva_importar_route_responds_when_unauthenticated(): void
    {
        $this->post(route('comges.carga-masiva-resultados.importar'), [])->assertRedirect();
    }

    public function test_pestanas_editables_masivo_route_responds_when_unauthenticated(): void
    {
        $this->post(route('comges.pestanas-editables.masivo'), [])->assertRedirect();
    }
}
