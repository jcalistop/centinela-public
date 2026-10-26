<?php

namespace App\Services\Adp;

use App\Models\AdpAgreementPeriod;
use App\Models\AdpFinalResult;
use App\Models\AdpFinalResultRow;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorResult;
use App\Models\AdpResultOverride;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdpFinalResultService
{
    public function __construct(
        private readonly AdpAppealService $appealService,
    ) {}

    public function publishFinal(
        AdpAgreementPeriod $period,
        ?int $publishedByUserId = null,
        ?string $withoutPreliminaryReason = null,
    ): AdpFinalResult {
        $snapshot = $this->appealService->latestPreliminarySnapshot($period);
        $reason = $withoutPreliminaryReason !== null ? trim($withoutPreliminaryReason) : '';

        if ($snapshot === null && $reason === '') {
            throw ValidationException::withMessages([
                'final' => 'No se puede publicar la evaluación final sin resultados preliminares, salvo que documente el motivo en «Publicación sin preliminar».',
            ]);
        }

        return DB::transaction(function () use ($period, $snapshot, $publishedByUserId, $reason): AdpFinalResult {
            $period->loadMissing('calculationResult', 'objectives.indicators');

            $nextVersion = (int) (AdpFinalResult::query()
                ->where('adp_agreement_period_id', $period->id)
                ->max('version') ?? 0) + 1;

            $calc = $period->calculationResult;

            /** @var AdpFinalResult $final */
            $final = AdpFinalResult::query()->create([
                'adp_agreement_period_id' => $period->id,
                'adp_preliminary_snapshot_id' => $snapshot?->id,
                'version' => $nextVersion,
                'weighted_global_percent' => $snapshot !== null
                    ? $snapshot->weighted_global_percent
                    : $calc?->weighted_global_percent,
                'published_without_preliminary' => $snapshot === null,
                'published_without_preliminary_reason' => $snapshot === null ? $reason : null,
                'published_at' => now(),
                'published_by_user_id' => $publishedByUserId,
            ]);

            foreach ($this->indicatorsForPeriod($period) as $indicator) {
                if ($snapshot !== null) {
                    $snapRow = $snapshot->rows()
                        ->where('adp_indicator_id', $indicator->id)
                        ->first();

                    AdpFinalResultRow::query()->create([
                        'adp_final_result_id' => $final->id,
                        'adp_indicator_id' => $indicator->id,
                        'preliminary_compliance_percent' => $snapRow?->compliance_percent,
                        'final_compliance_percent' => $snapRow?->compliance_percent,
                        'raw_result' => $snapRow?->raw_result,
                        'is_overridden' => false,
                    ]);
                } else {
                    $indRes = AdpIndicatorResult::query()
                        ->where('adp_agreement_period_id', $period->id)
                        ->where('adp_indicator_id', $indicator->id)
                        ->first();

                    AdpFinalResultRow::query()->create([
                        'adp_final_result_id' => $final->id,
                        'adp_indicator_id' => $indicator->id,
                        'preliminary_compliance_percent' => null,
                        'final_compliance_percent' => $indRes?->compliance_percent,
                        'raw_result' => $indRes?->raw_result,
                        'is_overridden' => false,
                    ]);
                }
            }

            return $final->load(['rows.indicator', 'preliminarySnapshot']);
        });
    }

    public function applyComplianceOverride(
        AdpFinalResult $final,
        AdpIndicator $indicator,
        string $newCompliancePercent,
        string $reason,
        ?int $userId = null,
    ): AdpFinalResultRow {
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'La justificación del override es obligatoria.']);
        }

        $row = AdpFinalResultRow::query()
            ->where('adp_final_result_id', $final->id)
            ->where('adp_indicator_id', $indicator->id)
            ->firstOrFail();

        $previous = $row->final_compliance_percent;
        $new = (string) $newCompliancePercent;

        return DB::transaction(function () use ($final, $indicator, $row, $previous, $new, $reason, $userId): AdpFinalResultRow {
            AdpResultOverride::query()->create([
                'adp_final_result_id' => $final->id,
                'adp_indicator_id' => $indicator->id,
                'field_name' => 'final_compliance_percent',
                'previous_value' => $previous,
                'new_value' => $new,
                'reason' => $reason,
                'user_id' => $userId,
            ]);

            $row->update([
                'final_compliance_percent' => $new,
                'is_overridden' => true,
            ]);

            return $row->fresh();
        });
    }

    public function latestFinalForPeriod(AdpAgreementPeriod $period): ?AdpFinalResult
    {
        return AdpFinalResult::query()
            ->where('adp_agreement_period_id', $period->id)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return Collection<int, AdpIndicator>
     */
    private function indicatorsForPeriod(AdpAgreementPeriod $period): Collection
    {
        return $period->objectives
            ->sortBy('orden')
            ->flatMap(fn ($o) => $o->indicators->sortBy('orden'));
    }
}
