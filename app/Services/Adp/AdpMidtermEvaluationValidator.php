<?php

namespace App\Services\Adp;

use App\Models\AdpAgreementPeriod;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class AdpMidtermEvaluationValidator
{
    /** Días de tolerancia alrededor de inicio del periodo + 6 meses (ADR / implementación). */
    public const TOLERANCE_DAYS = 14;

    public static function assertEvaluatedOnAllowed(AdpAgreementPeriod $period, Carbon $evaluatedOn): void
    {
        $expected = $period->expectedMidtermDate()->startOfDay();
        $eval = $evaluatedOn->copy()->startOfDay();

        $min = $expected->copy()->subDays(self::TOLERANCE_DAYS);
        $max = $expected->copy()->addDays(self::TOLERANCE_DAYS);

        if ($eval->lt($min) || $eval->gt($max)) {
            throw ValidationException::withMessages([
                'evaluated_on' => 'La evaluación parcial debe registrarse en la ventana de 6 meses (± '
                    .self::TOLERANCE_DAYS.' días) desde el inicio del periodo anual.',
            ]);
        }

        $pStart = $period->start_date->copy()->startOfDay();
        $pEnd = $period->end_date->copy()->startOfDay();
        if ($eval->lt($pStart) || $eval->gt($pEnd)) {
            throw ValidationException::withMessages([
                'evaluated_on' => 'La fecha de evaluación debe caer dentro del periodo anual del convenio.',
            ]);
        }
    }
}
