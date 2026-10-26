<?php

namespace App\Services\Alerts\Providers;

use App\Contracts\Alerts\VisualAlertProviderInterface;
use App\Enums\Comges\DocumentoVerificableType;
use App\Enums\Corte\CutoffClave;
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
use Illuminate\Support\Facades\Route;

class ComgesDashboardListProvider implements VisualAlertProviderInterface
{
    public function module(): VisualAlertModule
    {
        return VisualAlertModule::Comges;
    }

    public function supports(AlertContext $context): bool
    {
        return $context->surface === AlertSurface::Dashboard && $context->usesScoreScale;
    }

    public function requiredEagerLoads(AlertContext $context): array
    {
        return [
            'comIndicators.comIndicatorCutoffs.cutoff',
            'comIndicators.comIndicatorCutoffs.comActions.comSensitivityTables.comSensitivityDependencies',
            'comIndicators.comIndicatorCutoffs.comActions.comVerifiableDocuments',
        ];
    }

    public function collect(AlertContext $context, iterable $subjects): Collection
    {
        $alerts = collect();
        $hieId = $context->cenHieDependencyId;

        if ($hieId === null) {
            return $alerts;
        }

        $assignedIndicatorIds = $this->assignedIndicatorIdsForReferente($context);

        foreach ($subjects as $commitment) {
            if (! $commitment instanceof ComCommitment) {
                continue;
            }

            $compNombre = (string) ($commitment->nombre ?: $commitment->clave);

            foreach ($commitment->comIndicators as $indicator) {
                $indNombre = (string) ($indicator->nombre ?: $indicator->clave);
                $indicatorUrl = $this->indicatorShowUrl((int) $indicator->id);

                foreach ($indicator->comIndicatorCutoffs as $cutoff) {
                    if ($cutoff->cutoff?->clave === CutoffClave::Apelacion) {
                        continue;
                    }

                    $corteNombre = (string) ($cutoff->cutoff?->nombre_corte ?? $cutoff->cutoff?->clave ?? 'corte');

                    if (
                        $context->user->hasRole('control-gestion')
                        && $cutoff->hasSensitivityTableSelectionPendingForDependency($hieId)
                    ) {
                        $alerts->push($this->dashboardAlert(
                            'comges:dash:sensitivity:'.$cutoff->id,
                            'COMGES: sensibilidad pendiente',
                            'Seleccione la tabla de sensibilidad para el corte «'.$corteNombre.'» del indicador «'.$indNombre.'» en el compromiso «'.$compNombre.'».',
                            $indicatorUrl,
                            ['control-gestion', 'admin'],
                        ));
                    }

                    if ($context->user->hasRole('parametrizador')) {
                        $sum = 0.0;
                        foreach ($cutoff->comActions as $action) {
                            $sum += (float) ($action->ponderacion_accion ?? 0);
                        }

                        if (round($sum, 2) !== 100.0) {
                            $alerts->push($this->dashboardAlert(
                                'comges:dash:ponderacion:'.$cutoff->id,
                                'COMGES: ponderación de acciones',
                                'La suma de ponderaciones del corte «'.$corteNombre.'» del indicador «'.$indNombre.'» no es 100% en «'.$compNombre.'».',
                                $indicatorUrl,
                                ['parametrizador', 'admin'],
                            ));
                        }
                    }

                    if ($this->shouldEmitPendingVerificationAlert($context, $indicator, $cutoff, $hieId, $assignedIndicatorIds)) {
                        $alerts->push($this->dashboardAlert(
                            'comges:dash:mv-pending:'.$cutoff->id,
                            'Pendiente medios de verificación',
                            'El indicador «'.$indNombre.'» del compromiso «'.$compNombre.'» tiene medios de verificación pendientes en el corte «'.$corteNombre.'».',
                            $indicatorUrl,
                            ['control-gestion', 'referente'],
                        ));
                    }
                }
            }
        }

        return $alerts;
    }

    /**
     * @param  array<int, true>|null  $assignedIndicatorIds  null = CG (sin filtro); array = referente
     */
    private function shouldEmitPendingVerificationAlert(
        AlertContext $context,
        ComIndicator $indicator,
        ComIndicatorCutoff $cutoff,
        int $hieId,
        ?array $assignedIndicatorIds,
    ): bool {
        $role = $context->roleName();

        if ($role === 'control-gestion') {
            return $this->cutoffHasPendingEvaluationDocuments($cutoff, $hieId);
        }

        if ($role !== 'referente') {
            return false;
        }

        if ($assignedIndicatorIds === null || ! isset($assignedIndicatorIds[(int) $indicator->id])) {
            return false;
        }

        return $this->cutoffHasPendingEvaluationDocuments($cutoff, $hieId);
    }

    private function cutoffHasPendingEvaluationDocuments(ComIndicatorCutoff $cutoff, int $hieId): bool
    {
        if (! ComgesVerifiableDocumentEvaluator::cutoffHasAnyAction($cutoff)) {
            return false;
        }

        return ComgesVerifiableDocumentEvaluator::hasEmptyDocumentForType(
            $cutoff->comActions,
            $hieId,
            DocumentoVerificableType::Evaluacion,
        );
    }

    /**
     * Indicadores COMGES donde el referente está en el pivot del SS.
     * null = no aplica (no es referente).
     *
     * @return array<int, true>|null
     */
    private function assignedIndicatorIdsForReferente(AlertContext $context): ?array
    {
        if ($context->roleName() !== 'referente' || $context->cenHieDependencyId === null) {
            return null;
        }

        $ids = ComIndicator::query()
            ->whereHas('comIndicatorSettings', function ($query) use ($context): void {
                $query->where('cen_hie_dependency_id', $context->cenHieDependencyId)
                    ->whereHas('users', fn ($users) => $users->whereKey($context->user->getKey()));
            })
            ->pluck('id');

        $set = [];
        foreach ($ids as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }

    /**
     * @param  list<string>  $roles
     */
    private function dashboardAlert(
        string $id,
        string $title,
        string $body,
        string $url,
        array $roles,
    ): VisualAlert {
        return new VisualAlert(
            id: $id,
            module: VisualAlertModule::Comges,
            severity: VisualAlertSeverity::Warning,
            placement: VisualAlertPlacement::DashboardList,
            title: $title,
            anchorKey: 'dashboard',
            priority: 50,
            detail: $body,
            url: $url,
            roles: $roles,
        );
    }

    private function indicatorShowUrl(int $indicatorId): string
    {
        if (Route::has('voyager.indicadores-comges.show')) {
            return route('voyager.indicadores-comges.show', $indicatorId);
        }

        return url('/admin/indicadores-comges/'.$indicatorId);
    }
}
