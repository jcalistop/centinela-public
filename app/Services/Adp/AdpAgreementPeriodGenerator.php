<?php

namespace App\Services\Adp;

use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class AdpAgreementPeriodGenerator
{
    /**
     * Periodos anuales móviles: cada año comienza el aniversario del inicio del convenio
     * (p. ej. 2026-03-15 … 2027-03-14), durante {@see AdpAgreement::$duration_years} años.
     */
    public function replacePeriodsForAgreement(AdpAgreement $agreement): void
    {
        DB::transaction(function () use ($agreement): void {
            $agreement->periods()->delete();

            $start = Carbon::parse($agreement->agreement_start)->startOfDay();

            for ($i = 1; $i <= $agreement->duration_years; $i++) {
                $periodStart = $start->copy()->addYears($i - 1);
                $periodEnd = $periodStart->copy()->addYear()->subDay();

                AdpAgreementPeriod::query()->create([
                    'adp_agreement_id' => $agreement->id,
                    'sequence' => $i,
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                ]);
            }
        });
    }
}
