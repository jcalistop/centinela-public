<?php

namespace App\Http\Controllers;

use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorAppeal;
use App\Models\AdpMidtermEvaluation;
use App\Models\AdpMonthlyData;
use App\Models\IndicatorType;
use App\Services\Adp\AdpAppealService;
use App\Services\Adp\AdpCalculationService;
use App\Services\Adp\AdpFinalResultService;
use App\Services\Adp\AdpMonthlyDataValidator;
use App\Services\Adp\AdpPreliminarySnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdpSelfEvaluationController extends Controller
{
    public function agreements(): View
    {
        Gate::authorize('viewAny', AdpAgreement::class);

        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();

        $agreements = AdpAgreement::query()
            ->with(['assignment.user', 'assignment.center', 'periods'])
            ->orderByDesc('id')
            ->get();

        return view('centinela.adp.self_evaluation.agreements', compact('agreements', 'indicatorType'));
    }

    public function agreement(AdpAgreement $agreement): View
    {
        Gate::authorize('read', $agreement);

        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();

        $agreement->load(['periods.objectives.indicators', 'assignment.center']);

        return view('centinela.adp.self_evaluation.agreement', compact('agreement', 'indicatorType'));
    }

    public function indicatorMonthly(AdpAgreement $agreement, AdpAgreementPeriod $period, AdpIndicator $indicator): View
    {
        Gate::authorize('read', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);
        $this->assertIndicatorBelongsToPeriod($period, $indicator);

        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();

        $monthStarts = $period->monthStartsForDataEntry();
        $rows = AdpMonthlyData::query()
            ->where('adp_indicator_id', $indicator->id)
            ->where('adp_agreement_period_id', $period->id)
            ->get()
            ->keyBy(fn (AdpMonthlyData $r) => $r->month_date->format('Y-m-d'));

        return view('centinela.adp.self_evaluation.indicator_monthly', compact(
            'indicatorType',
            'agreement',
            'period',
            'indicator',
            'monthStarts',
            'rows',
        ));
    }

    public function storeMonthly(Request $request, AdpAgreement $agreement, AdpAgreementPeriod $period, AdpIndicator $indicator): RedirectResponse
    {
        Gate::authorize('edit', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);
        $this->assertIndicatorBelongsToPeriod($period, $indicator);

        $validated = $request->validate([
            'month_date' => 'required|date',
            'valor' => 'nullable|numeric',
            'observacion' => 'nullable|string|max:10000',
        ]);

        $normalized = AdpMonthlyDataValidator::normalizeMonthDate($validated['month_date'])->toDateString();
        $allowed = $period->monthStartsForDataEntry()->map(fn ($c) => $c->toDateString())->all();

        if (! in_array($normalized, $allowed, true)) {
            return redirect()->back()->withErrors(['month_date' => 'Mes no permitido para este periodo.'])->withInput();
        }

        AdpMonthlyData::query()->updateOrCreate(
            [
                'adp_indicator_id' => $indicator->id,
                'adp_agreement_period_id' => $period->id,
                'month_date' => $normalized,
            ],
            [
                'valor' => $validated['valor'] ?? null,
                'observacion' => $validated['observacion'] ?? null,
            ]
        );

        return redirect()
            ->route('adp.self_evaluation.indicator.monthly', [$agreement, $period, $indicator])
            ->with('flash_monthly', 'Carga mensual guardada.');
    }

    public function midtermForm(AdpAgreement $agreement, AdpAgreementPeriod $period): View
    {
        Gate::authorize('read', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();

        $evaluation = AdpMidtermEvaluation::query()
            ->where('adp_agreement_period_id', $period->id)
            ->first();

        $expectedMid = $period->expectedMidtermDate();

        return view('centinela.adp.self_evaluation.midterm', compact(
            'indicatorType',
            'agreement',
            'period',
            'evaluation',
            'expectedMid',
        ));
    }

    public function midtermStore(Request $request, AdpAgreement $agreement, AdpAgreementPeriod $period): RedirectResponse
    {
        Gate::authorize('edit', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        $validated = $request->validate([
            'evaluated_on' => 'required|date',
            'observaciones' => 'nullable|string|max:10000',
            'status' => 'nullable|string|in:draft,submitted|max:24',
        ]);

        AdpMidtermEvaluation::query()->updateOrCreate(
            ['adp_agreement_period_id' => $period->id],
            [
                'evaluated_on' => $validated['evaluated_on'],
                'observaciones' => $validated['observaciones'] ?? null,
                'status' => $validated['status'] ?? 'submitted',
            ]
        );

        return redirect()
            ->route('adp.self_evaluation.midterm', [$agreement, $period])
            ->with('flash_midterm', 'Evaluación parcial registrada.');
    }

    public function recalculate(AdpAgreement $agreement, AdpAgreementPeriod $period): RedirectResponse
    {
        Gate::authorize('edit', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        try {
            $result = app(AdpCalculationService::class)->recalculatePeriod($period);
        } catch (ValidationException $e) {
            return redirect()
                ->route('adp.self_evaluation.agreement', $agreement)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('adp.self_evaluation.agreement', $agreement)
            ->with(
                'flash_calc',
                sprintf(
                    'Cálculo completado: global %s%% (%d/%d indicadores calculados, %d con error).',
                    $result->weighted_global_percent !== null ? (string) $result->weighted_global_percent : '—',
                    $result->n_calculated,
                    $result->n_indicators,
                    $result->n_failed
                )
            );
    }

    public function preliminaryShow(AdpAgreement $agreement, AdpAgreementPeriod $period): View
    {
        Gate::authorize('read', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();
        $snapshot = app(AdpAppealService::class)->latestPreliminarySnapshot($period);
        if ($snapshot !== null) {
            $snapshot->load(['rows.indicator']);
        }

        return view('centinela.adp.self_evaluation.preliminary', compact(
            'indicatorType',
            'agreement',
            'period',
            'snapshot',
        ));
    }

    public function preliminaryStore(AdpAgreement $agreement, AdpAgreementPeriod $period): RedirectResponse
    {
        Gate::authorize('edit', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        try {
            app(AdpPreliminarySnapshotService::class)->createSnapshot($period, auth()->id());
        } catch (ValidationException $e) {
            return redirect()
                ->route('adp.self_evaluation.preliminary', [$agreement, $period])
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('adp.self_evaluation.preliminary', [$agreement, $period])
            ->with('flash_preliminary', 'Evaluación preliminar generada (nueva versión de snapshot).');
    }

    public function appealsIndex(AdpAgreement $agreement, AdpAgreementPeriod $period): View
    {
        Gate::authorize('read', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();
        $appeals = AdpIndicatorAppeal::query()
            ->where('adp_agreement_period_id', $period->id)
            ->with(['indicator', 'preliminarySnapshot'])
            ->orderByDesc('id')
            ->get();

        return view('centinela.adp.self_evaluation.appeals', compact(
            'indicatorType',
            'agreement',
            'period',
            'appeals',
        ));
    }

    public function appealStore(Request $request, AdpAgreement $agreement, AdpAgreementPeriod $period, AdpIndicator $indicator): RedirectResponse
    {
        Gate::authorize('edit', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);
        $this->assertIndicatorBelongsToPeriod($period, $indicator);

        $validated = $request->validate([
            'motivo' => 'required|string|max:20000',
        ]);

        try {
            app(AdpAppealService::class)->createAppeal($period, $indicator, $validated['motivo']);
        } catch (ValidationException $e) {
            return redirect()
                ->route('adp.self_evaluation.preliminary', [$agreement, $period])
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('adp.self_evaluation.appeals', [$agreement, $period])
            ->with('flash_appeal', 'Apelación registrada.');
    }

    public function appealResolve(Request $request, AdpAgreement $agreement, AdpIndicatorAppeal $appeal): RedirectResponse
    {
        Gate::authorize('edit', $agreement);
        $this->assertAppealBelongsToAgreement($agreement, $appeal);

        $validated = $request->validate([
            'status' => 'required|string|in:accepted,rejected',
            'resolucion' => 'required|string|max:20000',
        ]);

        $map = [
            'accepted' => AdpIndicatorAppeal::STATUS_ACCEPTED,
            'rejected' => AdpIndicatorAppeal::STATUS_REJECTED,
        ];

        try {
            app(AdpAppealService::class)->resolveAppeal(
                $appeal,
                $map[$validated['status']],
                $validated['resolucion'],
                auth()->id(),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('adp.self_evaluation.appeals', [$agreement, $appeal->agreementPeriod])
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('adp.self_evaluation.appeals', [$agreement, $appeal->agreementPeriod])
            ->with('flash_appeal', 'Apelación resuelta.');
    }

    public function finalShow(AdpAgreement $agreement, AdpAgreementPeriod $period): View
    {
        Gate::authorize('read', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();
        $final = app(AdpFinalResultService::class)->latestFinalForPeriod($period);
        if ($final !== null) {
            $final->load(['rows.indicator', 'preliminarySnapshot', 'overrides.user']);
        }

        return view('centinela.adp.self_evaluation.final', compact(
            'indicatorType',
            'agreement',
            'period',
            'final',
        ));
    }

    public function finalPublish(Request $request, AdpAgreement $agreement, AdpAgreementPeriod $period): RedirectResponse
    {
        Gate::authorize('edit', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        $validated = $request->validate([
            'without_preliminary_reason' => 'nullable|string|max:20000',
        ]);

        try {
            app(AdpFinalResultService::class)->publishFinal(
                $period,
                auth()->id(),
                $validated['without_preliminary_reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('adp.self_evaluation.final', [$agreement, $period])
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('adp.self_evaluation.final', [$agreement, $period])
            ->with('flash_final', 'Evaluación final publicada (nueva versión).');
    }

    public function finalOverride(Request $request, AdpAgreement $agreement, AdpAgreementPeriod $period): RedirectResponse
    {
        Gate::authorize('edit', $agreement);
        $this->assertPeriodBelongsToAgreement($agreement, $period);

        $validated = $request->validate([
            'adp_indicator_id' => 'required|integer|exists:adp_indicators,id',
            'new_compliance_percent' => 'required|numeric',
            'reason' => 'required|string|max:20000',
        ]);

        $indicator = AdpIndicator::query()->findOrFail((int) $validated['adp_indicator_id']);
        $this->assertIndicatorBelongsToPeriod($period, $indicator);

        $final = app(AdpFinalResultService::class)->latestFinalForPeriod($period);
        if ($final === null) {
            return redirect()
                ->route('adp.self_evaluation.final', [$agreement, $period])
                ->withErrors(['final' => 'No hay evaluación final publicada para aplicar overrides.']);
        }

        try {
            app(AdpFinalResultService::class)->applyComplianceOverride(
                $final,
                $indicator,
                (string) $validated['new_compliance_percent'],
                $validated['reason'],
                auth()->id(),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('adp.self_evaluation.final', [$agreement, $period])
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('adp.self_evaluation.final', [$agreement, $period])
            ->with('flash_final', 'Override registrado con trazabilidad.');
    }

    private function assertPeriodBelongsToAgreement(AdpAgreement $agreement, AdpAgreementPeriod $period): void
    {
        abort_unless((int) $period->adp_agreement_id === (int) $agreement->id, 404);
    }

    private function assertIndicatorBelongsToPeriod(AdpAgreementPeriod $period, AdpIndicator $indicator): void
    {
        $indicator->loadMissing('objective');
        abort_unless(
            $indicator->objective !== null
            && (int) $indicator->objective->adp_agreement_period_id === (int) $period->id,
            404
        );
    }

    private function assertAppealBelongsToAgreement(AdpAgreement $agreement, AdpIndicatorAppeal $appeal): void
    {
        $appeal->loadMissing('agreementPeriod');
        abort_unless(
            $appeal->agreementPeriod !== null
            && (int) $appeal->agreementPeriod->adp_agreement_id === (int) $agreement->id,
            404
        );
    }
}
