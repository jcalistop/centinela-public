<?php

namespace App\Providers;

use App\Enums\Comges\DocumentoVerificableType;
use App\Enums\Indicators\IndicatorActivityAction;
use App\Enums\Indicators\IndicatorModuleType;
use App\Models\ComAction;
use App\Models\ComEvaluation;
use App\Models\ComIndicatorSetting;
use App\Models\ComSensitivityDependency;
use App\Models\ComVerifiableDocument;
use App\Models\MetFieldSsConfig;
use App\Models\MetFinalEvaluation;
use App\Models\MetIndicatorAppeal;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMonthlyData;
use App\Models\MetPreliminaryEvaluation;
use App\Services\Indicators\IndicatorActivityLogger;
use Illuminate\Support\ServiceProvider;

class IndicatorActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $logger = $this->app->make(IndicatorActivityLogger::class);

        ComIndicatorSetting::created(function (ComIndicatorSetting $setting) use ($logger): void {
            $logger->log(
                IndicatorModuleType::Comges,
                (int) $setting->com_indicator_id,
                IndicatorActivityAction::ConfigChanged,
                'Se creó la configuración del indicador para el Servicio de Salud.',
                ['cen_hie_dependency_id' => $setting->cen_hie_dependency_id],
            );
        });

        ComIndicatorSetting::updated(function (ComIndicatorSetting $setting) use ($logger): void {
            $changes = collect($setting->getChanges())
                ->except(['updated_at', 'created_at'])
                ->all();

            if ($changes === []) {
                return;
            }

            $logger->log(
                IndicatorModuleType::Comges,
                (int) $setting->com_indicator_id,
                IndicatorActivityAction::ConfigChanged,
                $logger->describeFieldChanges($changes),
                [
                    'cen_hie_dependency_id' => $setting->cen_hie_dependency_id,
                    'changes' => $changes,
                ],
            );
        });

        ComSensitivityDependency::created(function (ComSensitivityDependency $dependency) use ($logger): void {
            $indicatorId = $this->resolveComIndicatorIdFromSensitivityDependency($dependency);
            if ($indicatorId === null) {
                return;
            }

            $logger->log(
                IndicatorModuleType::Comges,
                $indicatorId,
                IndicatorActivityAction::SensitivitySelected,
                'Se seleccionó tabla de sensibilidad para una acción del indicador.',
                ['cen_hie_dependency_id' => $dependency->cen_hie_dependency_id],
            );
        });

        ComEvaluation::created(function (ComEvaluation $evaluation) use ($logger): void {
            if (IndicatorActivityLogger::isAutomaticLoggingSuppressed()) {
                return;
            }

            $this->logComEvaluation($logger, $evaluation);
        });

        ComEvaluation::updated(function (ComEvaluation $evaluation) use ($logger): void {
            if (IndicatorActivityLogger::isAutomaticLoggingSuppressed()) {
                return;
            }

            $changes = collect($evaluation->getChanges())
                ->only(['numerador', 'denominador', 'resultado', 'meta', 'cumplimiento'])
                ->all();

            if ($changes === []) {
                return;
            }

            $this->logComEvaluation($logger, $evaluation, $changes);
        });

        ComVerifiableDocument::created(function (ComVerifiableDocument $document) use ($logger): void {
            $indicatorId = $this->resolveComIndicatorIdFromActionId((int) $document->com_action_id);
            if ($indicatorId === null) {
                return;
            }

            $isAppeal = $document->verifiable_type === DocumentoVerificableType::Apelacion;

            $logger->log(
                IndicatorModuleType::Comges,
                $indicatorId,
                $isAppeal ? IndicatorActivityAction::AppealLoaded : IndicatorActivityAction::VerificationLoaded,
                $isAppeal
                    ? 'Se cargó una apelación del indicador.'
                    : 'Se cargó un medio de verificación del indicador.',
                [
                    'cen_hie_dependency_id' => $document->cen_hie_dependency_id,
                    'verifiable_type' => $document->verifiable_type?->value,
                ],
            );
        });

        MetIndicatorReferente::created(function (MetIndicatorReferente $referente) use ($logger): void {
            $logger->log(
                IndicatorModuleType::Metas,
                (int) $referente->met_indicator_id,
                IndicatorActivityAction::ReferentAssigned,
                'Se asignó un referente al indicador.',
                [
                    'cen_hie_dependency_id' => $referente->cen_hie_dependency_id,
                    'referente_user_id' => $referente->user_id,
                ],
            );
        });

        MetIndicatorConfiguration::created(function (MetIndicatorConfiguration $config) use ($logger): void {
            $this->logMetCenterConfiguration($logger, $config, 'Se aplicó configuración del indicador para un establecimiento.');
        });

        MetIndicatorConfiguration::updated(function (MetIndicatorConfiguration $config) use ($logger): void {
            $tracked = collect($config->getChanges())
                ->only(['aplica', 'agrupa_ss', 'seleccionado', 'ponderacion', 'meta_efectiva'])
                ->all();

            if ($tracked === []) {
                return;
            }

            $this->logMetCenterConfiguration($logger, $config, 'Se actualizó la aplicación del indicador para un establecimiento.', $tracked);
        });

        MetFieldSsConfig::created(function (MetFieldSsConfig $config) use ($logger): void {
            $this->logMetFieldSsConfig($logger, $config, 'Se configuró tipo de entrada o agregación.');
        });

        MetFieldSsConfig::updated(function (MetFieldSsConfig $config) use ($logger): void {
            $changes = collect($config->getChanges())
                ->only(['input_type', 'aggregation_type'])
                ->all();

            if ($changes === []) {
                return;
            }

            $this->logMetFieldSsConfig($logger, $config, 'Se actualizó tipo de entrada o agregación.', $changes);
        });

        MetMonthlyData::created(function (MetMonthlyData $monthlyData) use ($logger): void {
            if (IndicatorActivityLogger::isAutomaticLoggingSuppressed()) {
                return;
            }

            $field = $monthlyData->relationLoaded('dataField')
                ? $monthlyData->getRelation('dataField')
                : $monthlyData->dataField()->first();

            if ($field === null) {
                return;
            }

            $logger->bufferDataLoad(
                (int) $field->met_indicator_id,
                $monthlyData->cen_hie_dependency_id !== null ? (int) $monthlyData->cen_hie_dependency_id : null,
            );
        });

        MetPreliminaryEvaluation::created(function (MetPreliminaryEvaluation $evaluation) use ($logger): void {
            $this->logMetEvaluation($logger, $evaluation->met_indicator_id, $evaluation->cen_hie_dependency_id, 'preliminar');
        });

        MetFinalEvaluation::created(function (MetFinalEvaluation $evaluation) use ($logger): void {
            $this->logMetEvaluation($logger, $evaluation->met_indicator_id, $evaluation->cen_hie_dependency_id, 'final');
        });

        MetIndicatorAppeal::created(function (MetIndicatorAppeal $appeal) use ($logger): void {
            $this->logMetEvaluation($logger, $appeal->met_indicator_id, $appeal->cen_hie_dependency_id, 'apelación');
        });

        MetIndicatorAppeal::updated(function (MetIndicatorAppeal $appeal) use ($logger): void {
            $this->logMetEvaluation($logger, $appeal->met_indicator_id, $appeal->cen_hie_dependency_id, 'apelación (actualización)');
        });
    }

    private function resolveComIndicatorIdFromActionId(int $actionId): ?int
    {
        $action = ComAction::query()
            ->with('comIndicatorCutoff')
            ->find($actionId);

        return $action?->comIndicatorCutoff?->com_indicator_id
            ? (int) $action->comIndicatorCutoff->com_indicator_id
            : null;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function logComEvaluation(
        IndicatorActivityLogger $logger,
        ComEvaluation $evaluation,
        array $changes = [],
    ): void {
        $indicatorId = $this->resolveComIndicatorIdFromActionId((int) $evaluation->com_action_id);
        if ($indicatorId === null) {
            return;
        }

        $stage = $evaluation->tipo_evaluacion?->value ?? 'evaluación';
        $description = $changes === []
            ? "Se cargó la evaluación {$stage} del indicador."
            : "Se actualizó la evaluación {$stage} del indicador.";

        $metadata = [
            'cen_hie_dependency_id' => $evaluation->cen_hie_dependency_id,
            'tipo_evaluacion' => $evaluation->tipo_evaluacion?->value,
            'com_action_id' => $evaluation->com_action_id,
        ];

        if ($changes !== []) {
            $metadata['changes'] = $changes;
        }

        $logger->log(
            IndicatorModuleType::Comges,
            $indicatorId,
            IndicatorActivityAction::EvaluationLoaded,
            $description,
            $metadata,
        );
    }

    private function resolveComIndicatorIdFromSensitivityDependency(ComSensitivityDependency $dependency): ?int
    {
        $dependency->loadMissing('comSensitivityTable.comAction.comIndicatorCutoff');

        return $dependency->comSensitivityTable?->comAction?->comIndicatorCutoff?->com_indicator_id
            ? (int) $dependency->comSensitivityTable->comAction->comIndicatorCutoff->com_indicator_id
            : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logMetCenterConfiguration(
        IndicatorActivityLogger $logger,
        MetIndicatorConfiguration $config,
        string $description,
        array $metadata = [],
    ): void {
        $logger->log(
            IndicatorModuleType::Metas,
            (int) $config->met_indicator_id,
            IndicatorActivityAction::CenterApplied,
            $description,
            array_merge([
                'cen_hie_dependency_id' => $config->cen_hie_dependency_id,
                'cen_center_id' => $config->cen_center_id,
                'ponderacion' => $config->ponderacion,
                'meta_efectiva' => $config->meta_efectiva,
                'aplica' => $config->aplica,
                'agrupa_ss' => $config->agrupa_ss,
                'seleccionado' => $config->seleccionado,
            ], $metadata),
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function logMetFieldSsConfig(
        IndicatorActivityLogger $logger,
        MetFieldSsConfig $config,
        string $description,
        array $extra = [],
    ): void {
        $config->loadMissing('metDataField');
        $indicatorId = $config->metDataField?->met_indicator_id;
        if ($indicatorId === null) {
            return;
        }

        $logger->log(
            IndicatorModuleType::Metas,
            (int) $indicatorId,
            IndicatorActivityAction::InputAggregationChanged,
            $description,
            array_merge([
                'cen_hie_dependency_id' => $config->cen_hie_dependency_id,
                'met_data_field_id' => $config->met_data_field_id,
                'input_type' => $config->input_type?->value,
                'aggregation_type' => $config->aggregation_type?->value,
            ], $extra),
        );
    }

    private function logMetEvaluation(
        IndicatorActivityLogger $logger,
        int $indicatorId,
        ?int $ssId,
        string $stage,
    ): void {
        $logger->log(
            IndicatorModuleType::Metas,
            $indicatorId,
            IndicatorActivityAction::EvaluationLoaded,
            "Se cargó evaluación {$stage} del indicador.",
            ['cen_hie_dependency_id' => $ssId, 'stage' => $stage],
        );
    }
}
