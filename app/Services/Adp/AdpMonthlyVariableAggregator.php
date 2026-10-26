<?php

namespace App\Services\Adp;

use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpMonthlyData;

/**
 * Variables para el evaluador de fórmulas Metas: A = suma mensual, B = n° meses con valor, C = promedio (A/B).
 *
 * @return array<string, float|int|null>
 */
final class AdpMonthlyVariableAggregator
{
    public static function buildVariables(AdpIndicator $indicator, AdpAgreementPeriod $period): array
    {
        $agg = AdpMonthlyData::query()
            ->where('adp_indicator_id', $indicator->id)
            ->where('adp_agreement_period_id', $period->id)
            ->whereNotNull('valor')
            ->selectRaw('count(*) as c, coalesce(sum(valor),0) as s')
            ->first();

        $n = (int) ($agg->c ?? 0);
        $s = (float) ($agg->s ?? 0.0);

        return [
            'A' => $n > 0 ? $s : null,
            'B' => $n > 0 ? $n : null,
            'C' => $n > 0 ? $s / $n : null,
        ];
    }
}
