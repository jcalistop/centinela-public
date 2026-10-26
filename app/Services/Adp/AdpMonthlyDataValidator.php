<?php

namespace App\Services\Adp;

use App\Models\AdpAgreementPeriod;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class AdpMonthlyDataValidator
{
    /**
     * Valida que el mes calendario (primer día) intersecte el rango del periodo anual móvil.
     */
    public static function assertMonthDateInPeriod(Carbon $monthDate, AdpAgreementPeriod $period): void
    {
        $monthStart = $monthDate->copy()->startOfMonth()->startOfDay();
        $monthEnd = $monthDate->copy()->endOfMonth()->startOfDay();
        $pStart = $period->start_date->copy()->startOfDay();
        $pEnd = $period->end_date->copy()->startOfDay();

        if ($monthStart->gt($pEnd) || $monthEnd->lt($pStart)) {
            throw ValidationException::withMessages([
                'month_date' => 'El mes seleccionado no pertenece al periodo anual del convenio.',
            ]);
        }
    }

    public static function normalizeMonthDate(Carbon|string $value): Carbon
    {
        $d = Carbon::parse($value)->startOfDay();

        return $d->copy()->firstOfMonth();
    }
}
