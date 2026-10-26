<?php

namespace App\Jobs\Comges;

use App\Mail\Comges\ComgesEvaluationsImportReportMail;
use App\Models\ComAction;
use App\Models\ComEvaluation;
use App\Models\ComSensitivityTable;
use App\Models\User;
use App\Models\Year;
use App\Services\ComgesResultsService;
use App\Services\Indicators\IndicatorActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\SimpleExcel\SimpleExcelReader;

class ImportComgesEvaluationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $storedPath,
        public string $tipoEvaluacion,
        public int $userId,
        public int $cenHieDependencyId,
        public int $yearId,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        if (! $user instanceof User) {
            return;
        }

        Log::info('[comges.import] start', [
            'job' => self::class,
            'user_id' => $user->id,
            'ss_id' => $this->cenHieDependencyId,
            'year_id' => $this->yearId,
            'tipo_evaluacion' => $this->tipoEvaluacion,
            'stored_path' => $this->storedPath,
        ]);

        $expectedServicioSalud = (string) optional($user->cenHieDependency)->nombre;

        $errors = [];
        $processed = 0;
        $successful = 0;
        /** @var array<int, int> $indicatorRowCounts */
        $indicatorRowCounts = [];

        $fullPath = storage_path('app/'.ltrim($this->storedPath, '/'));

        $reader = SimpleExcelReader::create($fullPath);

        foreach ($reader->getRows() as $index => $row) {
            $processed++;
            $rowNumber = $index + 2;

            try {
                $indicatorId = $this->importRow((array) $row, $expectedServicioSalud);
                if ($indicatorId !== null) {
                    $indicatorRowCounts[$indicatorId] = ($indicatorRowCounts[$indicatorId] ?? 0) + 1;
                }
                $successful++;
            } catch (\Throwable $e) {
                $errors[] = "Fila {$rowNumber}: ".$e->getMessage();
            }
        }

        @unlink($fullPath);

        if ($indicatorRowCounts !== []) {
            $activityLogger = app(IndicatorActivityLogger::class);
            foreach ($indicatorRowCounts as $indicatorId => $rowCount) {
                $activityLogger->logBulkEvaluationImport(
                    $indicatorId,
                    $this->cenHieDependencyId,
                    $this->tipoEvaluacion,
                    $rowCount,
                    $user->id,
                );
            }
        }

        try {
            Mail::to($user->email)->send(new ComgesEvaluationsImportReportMail(
                tipoEvaluacion: $this->tipoEvaluacion,
                processed: $processed,
                successful: $successful,
                errors: $errors,
            ));

            Log::info('[comges.import] mail sent', [
                'user_id' => $user->id,
                'to' => $user->email,
                'processed' => $processed,
                'successful' => $successful,
                'errors' => count($errors),
            ]);
        } catch (\Throwable $e) {
            Log::error('[comges.import] mail failed', [
                'user_id' => $user->id,
                'to' => $user->email,
                'exception' => $e->getMessage(),
            ]);
        }

        Log::info('[comges.import] end', [
            'user_id' => $user->id,
            'processed' => $processed,
            'successful' => $successful,
            'errors' => count($errors),
        ]);

        if ($successful > 0) {
            ComgesResultsService::forgetCachedGlobalResults(
                $this->cenHieDependencyId,
                $this->yearId,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRow(array $row, string $expectedServicioSalud): ?int
    {
        $expectedYear = Year::query()->find($this->yearId);
        $expectedYearLabel = $expectedYear instanceof Year ? (string) $expectedYear->ano : '';

        $rowTipoEvaluacion = isset($row['tipo_evaluacion']) ? strtoupper(trim((string) $row['tipo_evaluacion'])) : '';
        $rowServicioSalud = isset($row['servicio_salud']) ? trim((string) $row['servicio_salud']) : '';
        $rowAno = isset($row['ano']) ? trim((string) $row['ano']) : '';

        if ($rowTipoEvaluacion !== '' && $rowTipoEvaluacion !== $this->tipoEvaluacion) {
            throw new \RuntimeException('La fila no corresponde al tipo de evaluación seleccionado');
        }

        if ($rowServicioSalud !== '' && $expectedServicioSalud !== '' && $rowServicioSalud !== $expectedServicioSalud) {
            throw new \RuntimeException('La fila no corresponde al Servicio de Salud activo de la sesión');
        }

        if ($rowAno !== '' && $expectedYearLabel !== '' && $rowAno !== $expectedYearLabel) {
            throw new \RuntimeException('La fila no corresponde al año activo de la sesión');
        }

        $comActionId = isset($row['com_action_id']) ? (int) $row['com_action_id'] : 0;
        if ($comActionId <= 0) {
            throw new \RuntimeException('com_action_id inválido');
        }

        $numerador = $this->toNullableFloat($row['numerador'] ?? null);
        $denominador = $this->toNullableFloat($row['denominador'] ?? null);

        if ($numerador === null || $denominador === null) {
            throw new \RuntimeException('numerador y denominador son requeridos');
        }

        if ($denominador <= 0) {
            throw new \RuntimeException('El denominador debe ser mayor a 0');
        }

        $comActionQuery = ComAction::query()
            ->whereKey($comActionId)
            ->whereHas('comIndicatorCutoff.comIndicator.comCommitment', function ($query): void {
                $query->where('year_id', $this->yearId);
            })
            ->whereHas('comIndicatorCutoff.cutoff', function ($query): void {
                $query->where('clave', '!=', 'APELACION');
            });

        if (in_array($this->tipoEvaluacion, ['PRELIMINAR', 'FINAL'], true)) {
            $comActionQuery->whereHas('comIndicatorCutoff', function ($query): void {
                $query->where('evaluacion_local', 0);
            });
        }

        $comAction = $comActionQuery->with('comIndicatorCutoff')->first();

        if (! $comAction instanceof ComAction) {
            throw new \RuntimeException('Acción no válida para el año activo o restricciones de corte/tipo de evaluación');
        }

        $indicatorId = (int) $comAction->comIndicatorCutoff->com_indicator_id;
        $calculated = $this->calculateEvaluationFields($comActionId, $numerador, $denominador);

        app(IndicatorActivityLogger::class)->withoutAutomaticLogging(function () use ($comActionId, $numerador, $denominador, $calculated): void {
            ComEvaluation::unguarded(function () use ($comActionId, $numerador, $denominador, $calculated): void {
                ComEvaluation::query()->updateOrCreate(
                    [
                        'com_action_id' => $comActionId,
                        'cen_hie_dependency_id' => $this->cenHieDependencyId,
                        'tipo_evaluacion' => $this->tipoEvaluacion,
                    ],
                    [
                        'numerador' => $numerador,
                        'denominador' => $denominador,
                        'resultado' => $calculated['resultado'],
                        'meta' => $calculated['meta'],
                        'cumplimiento' => $calculated['cumplimiento'],
                    ],
                );
            });
        });

        return $indicatorId;
    }

    /**
     * @return array{resultado: float, meta: string|null, cumplimiento: float}
     */
    private function calculateEvaluationFields(int $comActionId, float $numerador, float $denominador): array
    {
        $comSensitivityTable = $this->getSelectedSensitivityTable($comActionId);

        if (is_null($comSensitivityTable)) {
            throw new \RuntimeException('No existe una tabla de sensibilidad seleccionada para su Servicio de Salud');
        }

        $resultado = round(($numerador / $denominador) * 100, 1);
        $cumplimiento = $comSensitivityTable->resolveCumplimiento($resultado);

        if (is_null($cumplimiento)) {
            throw new \RuntimeException('El resultado calculado no coincide con ningún rango de la tabla de sensibilidad');
        }

        return [
            'resultado' => $resultado,
            'meta' => $comSensitivityTable->meta_numero === null ? null : (string) $comSensitivityTable->meta_numero,
            'cumplimiento' => $cumplimiento,
        ];
    }

    private function getSelectedSensitivityTable(int $comActionId): ?ComSensitivityTable
    {
        return ComSensitivityTable::query()
            ->where('com_action_id', $comActionId)
            ->whereHas('comSensitivityDependencies', function ($query): void {
                $query->where('cen_hie_dependency_id', $this->cenHieDependencyId);
            })
            ->first();
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = is_string($value) ? str_replace(',', '.', trim($value)) : $value;

        return is_numeric($value) ? (float) $value : null;
    }
}
