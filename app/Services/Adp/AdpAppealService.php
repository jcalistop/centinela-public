<?php

namespace App\Services\Adp;

use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorAppeal;
use App\Models\AdpPreliminarySnapshot;
use Illuminate\Validation\ValidationException;

class AdpAppealService
{
    public function createAppeal(
        AdpAgreementPeriod $period,
        AdpIndicator $indicator,
        string $motivo,
    ): AdpIndicatorAppeal {
        $snapshot = $this->latestPreliminarySnapshot($period);
        if ($snapshot === null) {
            throw ValidationException::withMessages([
                'appeal' => 'No existe evaluación preliminar para este periodo. Genere el snapshot antes de apelar.',
            ]);
        }

        $pending = AdpIndicatorAppeal::query()
            ->where('adp_agreement_period_id', $period->id)
            ->where('adp_indicator_id', $indicator->id)
            ->where('status', AdpIndicatorAppeal::STATUS_PENDING)
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'appeal' => 'Ya existe una apelación pendiente para este indicador.',
            ]);
        }

        return AdpIndicatorAppeal::query()->create([
            'adp_agreement_period_id' => $period->id,
            'adp_preliminary_snapshot_id' => $snapshot->id,
            'adp_indicator_id' => $indicator->id,
            'status' => AdpIndicatorAppeal::STATUS_PENDING,
            'motivo' => $motivo,
        ]);
    }

    public function resolveAppeal(
        AdpIndicatorAppeal $appeal,
        string $status,
        string $resolucion,
        ?int $resolvedByUserId = null,
    ): AdpIndicatorAppeal {
        if ($appeal->status !== AdpIndicatorAppeal::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'appeal' => 'Solo se pueden resolver apelaciones en estado pendiente.',
            ]);
        }

        if (! in_array($status, [AdpIndicatorAppeal::STATUS_ACCEPTED, AdpIndicatorAppeal::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Estado de resolución inválido.',
            ]);
        }

        $appeal->update([
            'status' => $status,
            'resolucion' => $resolucion,
            'resolved_by_user_id' => $resolvedByUserId,
            'resolved_at' => now(),
        ]);

        return $appeal->fresh();
    }

    public function latestPreliminarySnapshot(AdpAgreementPeriod $period): ?AdpPreliminarySnapshot
    {
        return AdpPreliminarySnapshot::query()
            ->where('adp_agreement_period_id', $period->id)
            ->orderByDesc('version')
            ->first();
    }

    public function withdrawAppeal(AdpIndicatorAppeal $appeal): void
    {
        if ($appeal->status !== AdpIndicatorAppeal::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'appeal' => 'Solo se pueden retirar apelaciones pendientes.',
            ]);
        }

        $appeal->update(['status' => AdpIndicatorAppeal::STATUS_WITHDRAWN]);
    }
}
