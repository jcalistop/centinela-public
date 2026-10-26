<?php

namespace App\Services\Adp;

use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorResult;
use App\Models\AdpIndicatorTarget;
use App\Models\AdpPreliminarySnapshot;
use App\Models\AdpPreliminarySnapshotRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdpPreliminarySnapshotService
{
    public function createSnapshot(AdpAgreementPeriod $period, ?int $createdByUserId = null): AdpPreliminarySnapshot
    {
        $period->loadMissing('calculationResult', 'objectives.indicators');

        $calc = $period->calculationResult;
        if ($calc === null) {
            throw ValidationException::withMessages([
                'period' => 'No hay resultados calculados para este periodo. Use «Recalcular» primero.',
            ]);
        }

        return DB::transaction(function () use ($period, $calc, $createdByUserId): AdpPreliminarySnapshot {
            $nextVersion = (int) (AdpPreliminarySnapshot::query()
                ->where('adp_agreement_period_id', $period->id)
                ->max('version') ?? 0) + 1;

            /** @var AdpPreliminarySnapshot $snapshot */
            $snapshot = AdpPreliminarySnapshot::query()->create([
                'adp_agreement_period_id' => $period->id,
                'version' => $nextVersion,
                'weighted_global_percent' => $calc->weighted_global_percent,
                'n_indicators' => $calc->n_indicators,
                'n_calculated' => $calc->n_calculated,
                'n_failed' => $calc->n_failed,
                'created_by_user_id' => $createdByUserId,
            ]);

            foreach ($this->indicatorsForPeriod($period) as $indicator) {
                $result = AdpIndicatorResult::query()
                    ->where('adp_agreement_period_id', $period->id)
                    ->where('adp_indicator_id', $indicator->id)
                    ->first();

                $target = AdpIndicatorTarget::query()
                    ->where('adp_indicator_id', $indicator->id)
                    ->first();

                AdpPreliminarySnapshotRow::query()->create([
                    'adp_preliminary_snapshot_id' => $snapshot->id,
                    'adp_indicator_id' => $indicator->id,
                    'raw_result' => $result?->raw_result,
                    'compliance_percent' => $result?->compliance_percent,
                    'weight_percent' => $target?->weight_percent,
                    'error_message' => $result?->error_message,
                ]);
            }

            return $snapshot->load('rows.indicator');
        });
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
