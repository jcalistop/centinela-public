<?php

namespace App\Services\Adp;

use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorTarget;
use Illuminate\Validation\ValidationException;

final class AdpPeriodWeightsValidator
{
    private const SUM_TOLERANCE = 0.02;

    /**
     * @param  AdpIndicatorTarget|null  $pending  Modelo en persistencia (atributos aún no en BD en creating).
     */
    public static function validateAgreementPeriod(AdpAgreementPeriod $period, ?AdpIndicatorTarget $pending = null): void
    {
        $indicators = AdpIndicator::query()
            ->whereHas('objective', function ($q) use ($period): void {
                $q->where('adp_agreement_period_id', $period->id);
            })
            ->with('target')
            ->get();

        $weights = [];
        foreach ($indicators as $indicator) {
            if ($pending !== null && (int) $pending->adp_indicator_id === (int) $indicator->id) {
                $weights[$indicator->id] = $pending->weight_percent;
            } else {
                $weights[$indicator->id] = $indicator->target?->weight_percent;
            }
        }

        foreach ($weights as $weight) {
            if ($weight === null) {
                continue;
            }
            $w = (float) $weight;
            if ($w < 10.0 - 1e-6) {
                throw ValidationException::withMessages([
                    'weight_percent' => 'Cada indicador con ponderación definida debe ponderar al menos 10%.',
                ]);
            }
            if ($w > 100.0 + 1e-6) {
                throw ValidationException::withMessages([
                    'weight_percent' => 'La ponderación de un indicador no puede superar 100%.',
                ]);
            }
        }

        if ($indicators->isEmpty()) {
            return;
        }

        $allDefined = collect($weights)->every(fn ($w) => $w !== null);

        if (! $allDefined) {
            return;
        }

        $sum = collect($weights)->sum(fn ($w) => (float) $w);

        if (abs($sum - 100.0) > self::SUM_TOLERANCE) {
            throw ValidationException::withMessages([
                'weight_percent' => 'Cuando todos los indicadores del periodo tienen ponderación, la suma debe ser 100%.',
            ]);
        }
    }

    /**
     * Tras eliminar un target, revalidar el periodo solo con lo que queda en BD.
     */
    public static function validateAgreementPeriodAfterDelete(AdpAgreementPeriod $period): void
    {
        self::validateAgreementPeriod($period, null);
    }

    /**
     * Exige que todos los indicadores del periodo tengan ponderación y sumen 100% (motor de cálculo SDD-030).
     */
    public static function assertPeriodReadyForCalculation(AdpAgreementPeriod $period): void
    {
        $indicators = AdpIndicator::query()
            ->whereHas('objective', function ($q) use ($period): void {
                $q->where('adp_agreement_period_id', $period->id);
            })
            ->with('target')
            ->get();

        if ($indicators->isEmpty()) {
            throw ValidationException::withMessages([
                'weights' => 'No hay indicadores en el periodo.',
            ]);
        }

        foreach ($indicators as $indicator) {
            $w = $indicator->target?->weight_percent;
            if ($w === null) {
                throw ValidationException::withMessages([
                    'weights' => 'Debe completar ponderaciones (100%) antes de calcular.',
                ]);
            }
            $wf = (float) $w;
            if ($wf < 10.0 - 1e-6 || $wf > 100.0 + 1e-6) {
                throw ValidationException::withMessages([
                    'weights' => 'Cada indicador debe ponderar entre 10% y 100%.',
                ]);
            }
        }

        $sum = $indicators->sum(fn (AdpIndicator $i) => (float) $i->target->weight_percent);

        if (abs($sum - 100.0) > self::SUM_TOLERANCE) {
            throw ValidationException::withMessages([
                'weights' => 'La suma de ponderaciones del periodo debe ser 100% para calcular resultados.',
            ]);
        }
    }
}
