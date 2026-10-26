<?php

namespace App\Services\Indicators;

use App\Enums\Indicators\IndicatorActivityAction;
use App\Enums\Indicators\IndicatorModuleType;
use App\Models\IndicatorActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class IndicatorActivityLogger
{
    /** @var array<string, array{count: int, indicator_id: int, ss_id: ?int}> */
    private static array $dataLoadBuffer = [];

    private static bool $terminatingRegistered = false;

    private static int $automaticLoggingSuppression = 0;

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutAutomaticLogging(callable $callback): mixed
    {
        self::$automaticLoggingSuppression++;

        try {
            return $callback();
        } finally {
            self::$automaticLoggingSuppression--;
        }
    }

    public static function isAutomaticLoggingSuppressed(): bool
    {
        return self::$automaticLoggingSuppression > 0;
    }

    public function log(
        IndicatorModuleType $module,
        int $indicatorId,
        IndicatorActivityAction $action,
        string $description,
        ?array $metadata = null,
        ?int $userId = null,
        bool $isPinned = false,
    ): IndicatorActivity {
        return IndicatorActivity::query()->create([
            'indicator_type' => $module,
            'indicator_id' => $indicatorId,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'is_pinned' => $isPinned,
            'user_id' => $userId ?? Auth::id(),
            'created_at' => Carbon::now(),
        ]);
    }

    public function bufferDataLoad(int $indicatorId, ?int $ssId): void
    {
        $key = $indicatorId.'|'.($ssId ?? 'global');
        if (! isset(self::$dataLoadBuffer[$key])) {
            self::$dataLoadBuffer[$key] = [
                'count' => 0,
                'indicator_id' => $indicatorId,
                'ss_id' => $ssId,
            ];
        }

        self::$dataLoadBuffer[$key]['count']++;

        if (! self::$terminatingRegistered) {
            self::$terminatingRegistered = true;
            app()->terminating(function (): void {
                $this->flushDataLoadBuffer();
            });
        }
    }

    public function flushDataLoadBuffer(): void
    {
        foreach (self::$dataLoadBuffer as $buffer) {
            if ($buffer['count'] < 1) {
                continue;
            }

            $count = $buffer['count'];
            $this->log(
                IndicatorModuleType::Metas,
                $buffer['indicator_id'],
                IndicatorActivityAction::DataLoaded,
                $count === 1
                    ? 'Se registró carga de datos del indicador.'
                    : "Se registró carga masiva de datos ({$count} registros).",
                [
                    'cen_hie_dependency_id' => $buffer['ss_id'],
                    'records' => $count,
                ],
            );
        }

        self::$dataLoadBuffer = [];
        self::$terminatingRegistered = false;
    }

    public function logBulkDataImport(
        int $indicatorId,
        int $ssId,
        int $recordCount,
        ?int $userId = null,
    ): IndicatorActivity {
        $description = $recordCount === 1
            ? 'Se registró carga masiva de datos (1 registro).'
            : "Se registró carga masiva de datos ({$recordCount} registros).";

        return $this->log(
            IndicatorModuleType::Metas,
            $indicatorId,
            IndicatorActivityAction::DataLoaded,
            $description,
            [
                'cen_hie_dependency_id' => $ssId,
                'records' => $recordCount,
                'source' => 'bulk_import',
            ],
            $userId,
        );
    }

    public function logBulkEvaluationImport(
        int $indicatorId,
        int $ssId,
        string $tipoEvaluacion,
        int $recordCount,
        int $userId,
    ): IndicatorActivity {
        $description = $recordCount === 1
            ? "Se registró carga masiva de evaluación {$tipoEvaluacion} (1 registro)."
            : "Se registró carga masiva de evaluación {$tipoEvaluacion} ({$recordCount} registros).";

        return $this->log(
            IndicatorModuleType::Comges,
            $indicatorId,
            IndicatorActivityAction::EvaluationLoaded,
            $description,
            [
                'cen_hie_dependency_id' => $ssId,
                'tipo_evaluacion' => $tipoEvaluacion,
                'records' => $recordCount,
                'source' => 'bulk_import',
            ],
            $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function describeFieldChanges(array $changes): string
    {
        if ($changes === []) {
            return 'Se actualizó la configuración del indicador.';
        }

        $labels = collect($changes)
            ->map(fn ($value, $field) => str_replace('_', ' ', (string) $field))
            ->implode(', ');

        return 'Se actualizó la configuración: '.$labels.'.';
    }
}
