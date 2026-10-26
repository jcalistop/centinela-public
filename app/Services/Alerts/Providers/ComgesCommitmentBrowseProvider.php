<?php

namespace App\Services\Alerts\Providers;

use App\Contracts\Alerts\VisualAlertProviderInterface;
use App\Enums\Comges\DocumentoVerificableType;
use App\Enums\Corte\CutoffClave;
use App\Enums\IndicatorType\EtapaVigente;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\ComIndicatorCutoff;
use App\Services\Alerts\ComgesVerifiableDocumentEvaluator;
use App\Support\Alerts\AlertContext;
use App\Support\Alerts\AlertSurface;
use App\Support\Alerts\VisualAlert;
use App\Support\Alerts\VisualAlertModule;
use App\Support\Alerts\VisualAlertPlacement;
use App\Support\Alerts\VisualAlertSeverity;
use Illuminate\Support\Collection;

class ComgesCommitmentBrowseProvider implements VisualAlertProviderInterface
{
    public function module(): VisualAlertModule
    {
        return VisualAlertModule::Comges;
    }

    public function supports(AlertContext $context): bool
    {
        return $context->surface === AlertSurface::Browse && $context->usesScoreScale;
    }

    public function requiredEagerLoads(AlertContext $context): array
    {
        if (! $context->usesScoreScale) {
            return [];
        }

        return [
            'comIndicators.comIndicatorCutoffs.cutoff',
            'comIndicators.comIndicatorCutoffs.comActions.comSensitivityTables.comSensitivityDependencies',
        ];
    }

    public function collect(AlertContext $context, iterable $subjects): Collection
    {
        $alerts = collect();
        $hieId = $context->cenHieDependencyId;

        foreach ($subjects as $commitment) {
            if (! $commitment instanceof ComCommitment) {
                continue;
            }

            foreach ($commitment->comIndicators as $indicator) {
                foreach ($indicator->comIndicatorCutoffs as $cutoff) {
                    $alerts = $alerts->merge(
                        $this->alertsForCutoff($context, $commitment, $indicator, $cutoff, $hieId)
                    );
                }
            }
        }

        return $alerts;
    }

    /**
     * @return Collection<int, VisualAlert>
     */
    private function alertsForCutoff(
        AlertContext $context,
        ComCommitment $commitment,
        ComIndicator $indicator,
        ComIndicatorCutoff $cutoff,
        ?int $hieId,
    ): Collection {
        if ($cutoff->cutoff?->clave === CutoffClave::Apelacion) {
            return $this->alertsForApelacionRow($context, $indicator);
        }

        $anchor = 'cutoff:'.$cutoff->id;
        $alerts = collect();

        if ($context->user->hasRole(['control-gestion', 'admin']) && $hieId !== null) {
            if ($cutoff->hasSensitivityTableSelectionPendingForDependency($hieId)) {
                $alerts->push(new VisualAlert(
                    id: 'comges:sensitivity:'.$cutoff->id,
                    module: VisualAlertModule::Comges,
                    severity: VisualAlertSeverity::Warning,
                    placement: VisualAlertPlacement::CutoffLabel,
                    title: 'Pendiente tabla de sensibilidad',
                    anchorKey: $anchor,
                    priority: 90,
                    detail: 'Seleccione la tabla de sensibilidad para su Servicio de Salud',
                    titleAttr: 'Control de Gestión debe seleccionar una tabla de sensibilidad para su Servicio de Salud en al menos una acción de este corte.',
                    roles: ['control-gestion', 'admin'],
                ));
            }
        }

        if ($context->user->hasRole(['parametrizador', 'admin'])) {
            $sum = 0.0;
            foreach ($cutoff->comActions as $action) {
                $sum += (float) ($action->ponderacion_accion ?? 0);
            }

            if (round($sum, 2) !== 100.0) {
                $alerts->push(new VisualAlert(
                    id: 'comges:ponderacion:'.$cutoff->id,
                    module: VisualAlertModule::Comges,
                    severity: VisualAlertSeverity::Warning,
                    placement: VisualAlertPlacement::CutoffLabel,
                    title: 'Ponderación de acciones incorrecta',
                    anchorKey: $anchor,
                    priority: 80,
                    detail: round($sum, 2).'% (debe ser 100%)',
                    titleAttr: 'La suma de ponderaciones de las acciones para este corte debe ser 100%.',
                    roles: ['parametrizador', 'admin'],
                ));
            }
        }

        if ($hieId !== null && ComgesVerifiableDocumentEvaluator::cutoffHasAnyAction($cutoff)) {
            $actions = $cutoff->comActions;
            $evalType = DocumentoVerificableType::Evaluacion;

            if (ComgesVerifiableDocumentEvaluator::hasEmptyDocumentForType($actions, $hieId, $evalType)) {
                $alerts->push(new VisualAlert(
                    id: 'comges:mv-pending:'.$cutoff->id,
                    module: VisualAlertModule::Comges,
                    severity: VisualAlertSeverity::Warning,
                    placement: VisualAlertPlacement::EvaluationAuto,
                    title: 'Pendiente medios de verificación',
                    anchorKey: $anchor,
                    priority: 70,
                ));
            } elseif (ComgesVerifiableDocumentEvaluator::allDocumentsLoadedForType($actions, $hieId, $evalType)) {
                $alerts->push(new VisualAlert(
                    id: 'comges:mv-loaded:'.$cutoff->id,
                    module: VisualAlertModule::Comges,
                    severity: VisualAlertSeverity::Success,
                    placement: VisualAlertPlacement::EvaluationAuto,
                    title: 'Medios de verificación cargados',
                    anchorKey: $anchor,
                    priority: 40,
                ));
            }

            if (! $cutoff->evaluacion_local) {
                $apelacionType = DocumentoVerificableType::Apelacion;

                if (ComgesVerifiableDocumentEvaluator::hasEmptyDocumentForType($actions, $hieId, $apelacionType)) {
                    $alerts->push(new VisualAlert(
                        id: 'comges:apel-pending:'.$cutoff->id,
                        module: VisualAlertModule::Comges,
                        severity: VisualAlertSeverity::Warning,
                        placement: VisualAlertPlacement::EvaluationPreliminar,
                        title: 'Pendiente apelación',
                        anchorKey: $anchor,
                        priority: 65,
                    ));
                } elseif (ComgesVerifiableDocumentEvaluator::allDocumentsLoadedForType($actions, $hieId, $apelacionType)) {
                    $alerts->push(new VisualAlert(
                        id: 'comges:apel-loaded:'.$cutoff->id,
                        module: VisualAlertModule::Comges,
                        severity: VisualAlertSeverity::Success,
                        placement: VisualAlertPlacement::EvaluationPreliminar,
                        title: 'Apelación cargada',
                        anchorKey: $anchor,
                        priority: 35,
                    ));
                }
            }
        }

        $alerts = $alerts->merge($this->appealOpportunityAlert($context, $cutoff, $anchor, false));

        return $alerts;
    }

    /**
     * @return Collection<int, VisualAlert>
     */
    private function alertsForApelacionRow(AlertContext $context, ComIndicator $indicator): Collection
    {
        $anchor = 'indicator-apel:'.$indicator->id;

        return $this->appealOpportunityAlert($context, null, $anchor, true, $indicator);
    }

    /**
     * @return Collection<int, VisualAlert>
     */
    private function appealOpportunityAlert(
        AlertContext $context,
        ?ComIndicatorCutoff $cutoff,
        string $anchor,
        bool $isApelacionRow,
        ?ComIndicator $indicator = null,
    ): Collection {
        $etapa = $context->comgesEtapaVigente;

        if ($etapa !== EtapaVigente::Preliminar && $etapa !== EtapaVigente::Apelacion) {
            return collect();
        }

        if ($isApelacionRow && $indicator !== null) {
            $auto = $indicator->puntajePonderado('AUTOEVALUACION');
            $preliminar = $indicator->puntajePonderado('PRELIMINAR');
        } elseif ($cutoff instanceof ComIndicatorCutoff) {
            $auto = $cutoff->promedioCumplimiento('AUTOEVALUACION');
            $preliminar = $cutoff->promedioCumplimiento('PRELIMINAR');
        } else {
            return collect();
        }

        if ($auto === null || $preliminar === null || $auto <= $preliminar) {
            return collect();
        }

        $suffix = $isApelacionRow ? 'ind-'.$indicator->id : (string) $cutoff->id;

        return collect([
            new VisualAlert(
                id: 'comges:appeal-opportunity:'.$suffix,
                module: VisualAlertModule::Comges,
                severity: VisualAlertSeverity::Warning,
                placement: VisualAlertPlacement::EvaluationPreliminar,
                title: '¿Oportunidad de apelación?',
                anchorKey: $anchor,
                priority: 60,
            ),
        ]);
    }
}
