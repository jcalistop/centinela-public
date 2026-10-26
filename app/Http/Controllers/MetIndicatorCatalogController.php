<?php

namespace App\Http\Controllers;

use App\Livewire\MetasInstrument\Concerns\AssertsMetIndicatorCatalogScope;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Services\Metas\MetIndicatorCalculationErrorService;
use App\Services\Metas\MetIndicatorCatalogPersistenceService;
use App\Services\Metas\MetIndicatorFormulaIssueService;
use App\Services\Metas\MetIndicatorIncompletePeriodService;
use App\Services\Metas\MetIndicatorLoadedDataDeletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class MetIndicatorCatalogController extends Controller
{
    use AssertsMetIndicatorCatalogScope;

    public function create(IndicatorType $indicatorType): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->authorizeParametrizador();

        return view('centinela.metas-instrument.catalog.create', compact('indicatorType'));
    }

    public function show(IndicatorType $indicatorType, MetIndicator $indicator): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->authorizeCatalogShow();
        $this->assertIndicatorInScope($indicator, $indicatorType);

        $yearId = $this->currentIndicatorYear()->id;
        $user = $this->authCatalogUser();
        $canManage = $user->hasRole(['parametrizador', 'admin']);
        $canViewDesempeno = $user->hasRole(['control-gestion', 'admin', 'referente', 'visualizador']);
        $hasLoadedData = app(MetIndicatorLoadedDataDeletionService::class)
            ->hasLoadedDataInAnyDependency($indicator, $yearId);
        $canDelete = $canManage && ! $hasLoadedData;
        $deleteBlockedReason = $hasLoadedData
            ? 'No se puede eliminar: existen datos cargados para este indicador.'
            : null;

        $calculationErrorSummary = null;
        $incompletePeriodSummary = null;
        $formulaIssue = null;
        if ($canViewDesempeno) {
            $indicator->load(['dataFields', 'formula']);
            $formulaIssue = app(MetIndicatorFormulaIssueService::class)->issueForIndicator($indicator);
            $calculationErrorSummary = app(MetIndicatorCalculationErrorService::class)->summaryForIndicator(
                (int) $indicator->id,
                (int) $user->cen_hie_dependency_id,
                $yearId,
            );
            $incompletePeriodSummary = app(MetIndicatorIncompletePeriodService::class)->summaryForIndicator(
                (int) $indicator->id,
                (int) $user->cen_hie_dependency_id,
                $yearId,
            );
        } elseif ($canManage) {
            $indicator->load(['dataFields', 'formula']);
            $formulaIssue = app(MetIndicatorFormulaIssueService::class)->issueForIndicator($indicator);
        }

        return view('centinela.metas-instrument.catalog.show', compact(
            'indicatorType',
            'indicator',
            'canManage',
            'canViewDesempeno',
            'canDelete',
            'deleteBlockedReason',
            'calculationErrorSummary',
            'incompletePeriodSummary',
            'formulaIssue',
        ));
    }

    public function desempeno(IndicatorType $indicatorType, MetIndicator $indicator): RedirectResponse
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->authorizeDesempenoView();
        $this->assertIndicatorInScope($indicator, $indicatorType);

        return redirect()
            ->route('metas.instrument.indicators.show', [$indicatorType, $indicator])
            ->withFragment('tab-desempeno');
    }

    public function edit(IndicatorType $indicatorType, MetIndicator $indicator): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->authorizeParametrizador();
        $this->assertIndicatorInScope($indicator, $indicatorType);

        return view('centinela.metas-instrument.catalog.edit', compact('indicatorType', 'indicator'));
    }

    public function destroy(IndicatorType $indicatorType, MetIndicator $indicator): RedirectResponse
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->authorizeParametrizador();
        $this->assertIndicatorInScope($indicator, $indicatorType);

        try {
            app(MetIndicatorCatalogPersistenceService::class)->deleteScoped(
                $indicator,
                (int) $indicatorType->id,
                $this->currentIndicatorYear()->id,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'No se puede eliminar este indicador.';

            return redirect()
                ->route('metas.instrument.indicators.show', [$indicatorType, $indicator])
                ->with([
                    'message' => $message,
                    'alert-type' => 'error',
                ]);
        }

        return redirect()
            ->route('metas.instrument.index', $indicatorType)
            ->with([
                'message' => 'Indicador eliminado.',
                'alert-type' => 'success',
            ]);
    }
}
