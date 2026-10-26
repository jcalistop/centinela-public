<?php

namespace App\Services\Metas;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentResponsible;
use App\Models\MetIndicator;
use App\Models\User;
use Illuminate\Support\Collection;

class MetEstablishmentResponsibleAccess
{
    public function designatedCenterId(User $user, int $yearId, int $indicatorTypeId): ?int
    {
        $centerId = MetEstablishmentResponsible::query()
            ->where('user_id', $user->id)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('cen_hie_dependency_id', $user->cen_hie_dependency_id)
            ->value('cen_center_id');

        return $centerId !== null ? (int) $centerId : null;
    }

    public function isDesignatedResponsible(User $user, int $yearId, int $indicatorTypeId): bool
    {
        return $this->designatedCenterId($user, $yearId, $indicatorTypeId) !== null;
    }

    public function isResponsibleForCenter(User $user, int $centerId, int $yearId, int $indicatorTypeId): bool
    {
        return $this->allowedCenterIdsForResponsible($user, $yearId, $indicatorTypeId)->contains($centerId);
    }

    /**
     * @return Collection<int, int>
     */
    public function allowedCenterIdsForResponsible(User $user, int $yearId, int $indicatorTypeId): Collection
    {
        $designatedCenterId = $this->designatedCenterId($user, $yearId, $indicatorTypeId);

        if ($designatedCenterId === null) {
            return collect();
        }

        $hieId = (int) $user->cen_hie_dependency_id;

        $isDesignatedAdosado = MetCenterAdosado::query()
            ->forInstrument($indicatorTypeId)
            ->where('cen_center_adosado_id', $designatedCenterId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $hieId)
            ->exists();

        if ($isDesignatedAdosado) {
            return collect([$designatedCenterId]);
        }

        return app(MetEstablishmentIndicatorLoadability::class)
            ->allowedEstablishmentCenterIdsIncludingAdosados(
                collect([$designatedCenterId]),
                $yearId,
                $hieId,
                $indicatorTypeId,
            );
    }

    public function canEditPonderaciones(User $user, int $centerId, int $yearId, int $indicatorTypeId): bool
    {
        $setting = IndicatorTypeSetting::getData($indicatorTypeId);

        if (! $setting || ! $setting->etapa_vigente->permiteFormulacionConvenio()) {
            return false;
        }

        if (! app(MetEstablishmentAgreementService::class)->isEditable($centerId, $yearId, $indicatorTypeId)) {
            return false;
        }

        if ($user->hasRole(['control-gestion', 'admin'])) {
            return true;
        }

        return $this->isResponsibleForCenter($user, $centerId, $yearId, $indicatorTypeId);
    }

    /**
     * Acceso a la vista de indicadores del centro (lectura o edición).
     * No exige FORMULACIÓN ni BORRADOR: un convenio ENVIADO sigue siendo consultable.
     */
    public function canViewEstablishmentIndicators(
        User $user,
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
    ): bool {
        if ($user->hasRole(['control-gestion', 'admin'])) {
            return true;
        }

        return $this->isResponsibleForCenter($user, $centerId, $yearId, $indicatorTypeId);
    }

    /**
     * CTA desde resultados del establecimiento: solo el responsable designado.
     * Control-gestión / admin editan convenio desde «Configurar establecimientos».
     * Visible también con convenio ENVIADO (solo lectura).
     */
    public function canShowConvenioCtaOnEstablishmentResults(
        User $user,
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
    ): bool {
        if ($user->hasRole(['control-gestion', 'admin', 'parametrizador'])) {
            return false;
        }

        $setting = IndicatorTypeSetting::getData($indicatorTypeId);

        if (! $setting || $setting->etapa_vigente === EtapaVigente::NoIniciado) {
            return false;
        }

        return $this->isResponsibleForCenter($user, $centerId, $yearId, $indicatorTypeId);
    }

    public function canLoadDataForCenter(
        User $user,
        MetIndicator $indicator,
        CenCenter $center,
        int $yearId,
        IndicatorType $indicatorType,
    ): bool {
        if (! $user->hasRole('referente')) {
            return false;
        }

        if (! $this->isDesignatedResponsible($user, $yearId, (int) $indicatorType->id)) {
            return false;
        }

        if (! $this->isResponsibleForCenter($user, (int) $center->id, $yearId, (int) $indicatorType->id)) {
            return false;
        }

        $assigned = MetIndicator::query()
            ->where('id', $indicator->id)
            ->whereHas('referentes', function ($q) use ($user, $yearId): void {
                $q->where('user_id', $user->id)
                    ->where('year_id', $yearId)
                    ->where('cen_hie_dependency_id', $user->cen_hie_dependency_id);
            })
            ->exists();

        if (! $assigned) {
            return false;
        }

        $indicator->loadMissing('dataFields');

        if (! app(MetFieldSsConfigService::class)->allowsReferenteDataEntry(
            $indicator,
            (int) $user->cen_hie_dependency_id,
            $yearId,
        )) {
            return false;
        }

        $setting = IndicatorTypeSetting::getData((int) $indicatorType->id);

        if (! $setting || ! $setting->etapa_vigente->permiteCargaMensual()) {
            return false;
        }

        $loadability = app(MetEstablishmentIndicatorLoadability::class);
        $hieId = (int) $user->cen_hie_dependency_id;

        if ($indicatorType->usesInternalUnitMeasurement()) {
            return MetCenterInclusion::query()
                ->where('cen_center_id', $center->id)
                ->where('year_id', $yearId)
                ->where('indicator_type_id', $indicatorType->id)
                ->where('cen_hie_dependency_id', $hieId)
                ->exists();
        }

        if (! $loadability->centerIsInMeasurementOrAdosadoOfIncludedMother(
            $center,
            $yearId,
            (int) $indicatorType->id,
            $hieId,
        )) {
            return false;
        }

        return $loadability->establishmentIndicatorAplicaForCenter(
            (int) $indicator->id,
            $center,
            $yearId,
            $hieId,
            (int) $indicatorType->id,
        );
    }

    public function userAlreadyResponsibleElsewhere(
        int $userId,
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
        int $cenHieDependencyId,
    ): bool {
        return MetEstablishmentResponsible::query()
            ->where('user_id', $userId)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->where('cen_center_id', '!=', $centerId)
            ->exists();
    }
}
