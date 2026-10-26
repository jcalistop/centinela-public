<?php

namespace App\Services\Metas;

use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\User;

class MetInstrumentDataEntryAccess
{
    public function canViewCenterDataEntry(
        User $user,
        IndicatorType $indicatorType,
        MetIndicator $indicator,
        CenCenter $center,
        int $yearId,
    ): bool {
        return $this->canDownloadComplexFormulaDataDetail($user, $indicatorType, $indicator, $center, $yearId);
    }

    public function complexFormulaDataDetailDownloadUrl(
        User $user,
        IndicatorType $indicatorType,
        MetIndicator $indicator,
        CenCenter $center,
        int $yearId,
        bool $forEstablishmentResultsView = false,
    ): ?string {
        $canDownload = $forEstablishmentResultsView
            ? $this->canDownloadComplexFormulaDataDetailForEstablishmentResultsView(
                $user,
                $indicatorType,
                $indicator,
                $center,
                $yearId,
            )
            : $this->canDownloadComplexFormulaDataDetail($user, $indicatorType, $indicator, $center, $yearId);

        if (! $canDownload) {
            return null;
        }

        return route('metas.instrument.indicador.detalle-datos.download', [
            'indicatorType' => $indicatorType,
            'indicator' => $indicator,
            'centers' => [$center->id],
        ]);
    }

    public function canDownloadComplexFormulaDataDetailForEstablishmentResultsView(
        User $user,
        IndicatorType $indicatorType,
        MetIndicator $indicator,
        CenCenter $center,
        int $yearId,
    ): bool {
        if ((int) $indicator->year_id !== $yearId) {
            return false;
        }

        if (! $user->hasRole('parametrizador')
            && (int) $center->cen_hie_dependency_id !== (int) $user->cen_hie_dependency_id) {
            return false;
        }

        if ($user->hasRole(['control-gestion', 'admin', 'parametrizador'])) {
            return true;
        }

        $loadability = app(MetEstablishmentIndicatorLoadability::class);
        $hieId = (int) $user->cen_hie_dependency_id;

        if (! $loadability->centerIsInMeasurementOrAdosadoOfIncludedMother(
            $center,
            $yearId,
            (int) $indicatorType->id,
            $hieId,
        )) {
            return false;
        }

        if (! $indicatorType->usesInternalUnitMeasurement()) {
            return $loadability->establishmentIndicatorAplicaForCenter(
                (int) $indicator->id,
                $center,
                $yearId,
                $hieId,
                (int) $indicatorType->id,
            );
        }

        $aplica = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_center_id', $center->id)
            ->where('year_id', $yearId)
            ->value('aplica');

        return $aplica === null || (bool) $aplica;
    }

    public function canDownloadComplexFormulaDataDetail(
        User $user,
        IndicatorType $indicatorType,
        MetIndicator $indicator,
        CenCenter $center,
        int $yearId,
    ): bool {
        if ((int) $center->cen_hie_dependency_id !== (int) $user->cen_hie_dependency_id) {
            return false;
        }

        if (! $user->hasRole(['referente', 'control-gestion', 'admin'])) {
            return false;
        }

        if ((int) $indicator->year_id !== $yearId || ! $indicator->estado) {
            return false;
        }

        if ($user->hasRole('referente')) {
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

            $responsibleAccess = app(MetEstablishmentResponsibleAccess::class);

            if ($responsibleAccess->isDesignatedResponsible($user, $yearId, (int) $indicatorType->id)) {
                return $responsibleAccess->isResponsibleForCenter($user, (int) $center->id, $yearId, (int) $indicatorType->id);
            }
        }

        if ($user->hasRole(['control-gestion', 'admin'])) {
            return true;
        }

        if ($user->hasRole('referente')) {
            $loadability = app(MetEstablishmentIndicatorLoadability::class);
            $hieId = (int) $user->cen_hie_dependency_id;

            if (! $loadability->centerIsInMeasurementOrAdosadoOfIncludedMother(
                $center,
                $yearId,
                (int) $indicatorType->id,
                $hieId,
            )) {
                return false;
            }

            if (! $indicatorType->usesInternalUnitMeasurement()) {
                return $loadability->establishmentIndicatorAplicaForCenter(
                    (int) $indicator->id,
                    $center,
                    $yearId,
                    $hieId,
                    (int) $indicatorType->id,
                );
            }

            $aplicaQuery = MetIndicatorConfiguration::query()
                ->where('met_indicator_id', $indicator->id)
                ->where('cen_center_id', $center->id)
                ->where('year_id', $yearId);

            $aplica = $aplicaQuery->value('aplica');

            return $aplica === null || (bool) $aplica;
        }

        return false;
    }
}
