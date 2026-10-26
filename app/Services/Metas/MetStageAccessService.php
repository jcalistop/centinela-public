<?php

namespace App\Services\Metas;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\MetEstablishmentResponsible;
use App\Models\User;

class MetStageAccessService
{
    public function canWriteMonthlyData(EtapaVigente $etapa): bool
    {
        return $etapa->permiteCargaMensual();
    }

    public function canWritePreliminary(EtapaVigente $etapa): bool
    {
        return $etapa === EtapaVigente::Preliminar;
    }

    public function canWriteAppeals(EtapaVigente $etapa): bool
    {
        return $etapa === EtapaVigente::Apelacion;
    }

    public function canWriteFinal(EtapaVigente $etapa): bool
    {
        return $etapa === EtapaVigente::Final;
    }

    public function canDesignateEstablishmentResponsible(User $user): bool
    {
        return $user->hasRole(['control-gestion', 'admin']);
    }

    public function canChangeEtapa(User $user): bool
    {
        return $user->hasRole(['control-gestion', 'admin']);
    }

    public function canAppeal(User $user, int $centerId, int $yearId, int $indicatorTypeId): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return MetEstablishmentResponsible::query()
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function canEditAppeals(
        User $user,
        EtapaVigente $etapa,
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
    ): bool {
        if ($etapa !== EtapaVigente::Apelacion) {
            return false;
        }

        return $this->canAppeal($user, $centerId, $yearId, $indicatorTypeId);
    }

    public function canViewAppeals(EtapaVigente $etapa): bool
    {
        return true;
    }

    public function canDownloadAppealAttachments(EtapaVigente $etapa): bool
    {
        return $this->canViewAppeals($etapa);
    }

    public function canWriteForEtapa(User $user, EtapaVigente $etapa): bool
    {
        if ($etapa === EtapaVigente::Autoevaluacion || $etapa === EtapaVigente::Formulacion) {
            return $user->hasRole(['control-gestion', 'admin', 'referente']);
        }

        if ($etapa === EtapaVigente::Apelacion) {
            return true;
        }

        return $user->hasRole(['control-gestion', 'admin']);
    }
}
