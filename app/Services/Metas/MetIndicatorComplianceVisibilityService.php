<?php

namespace App\Services\Metas;

use App\Enums\Metas\MetDataLoadStatus;
use App\Models\MetIndicator;
use App\Models\MetResult;
use App\Services\MetCalculationService;

/**
 * Criterio único de visibilidad del cumplimiento en Metas (SDD-334).
 *
 * Un indicador sin carga no tiene cumplimiento que mostrar: el catálogo (tab Desempeño) y la
 * vista de resultados por establecimiento deben decidir igual. La ausencia se decide por
 * `estado_carga` y por resultado nulo, nunca por el valor `0` (que es un caso con dato, §4.5).
 */
class MetIndicatorComplianceVisibilityService
{
    public function __construct(
        private readonly MetCalculationService $calculationService,
    ) {}

    public function sinDatos(
        ?float $resultado,
        MetDataLoadStatus $loadStatus,
        bool $hasTrackableLoadFields,
    ): bool {
        if ($resultado === null) {
            return true;
        }

        return $hasTrackableLoadFields
            && ($loadStatus === MetDataLoadStatus::SinDatos || $loadStatus === MetDataLoadStatus::ErrorCalculo);
    }

    /**
     * Estado de carga efectivo de una fila: normaliza el snapshot y promueve a `error_calculo`
     * cuando el resultado persistido trae un error de cálculo (nunca por alertas como `N/D`).
     */
    public function resolveLoadStatus(mixed $estadoCarga, ?string $storedMessage = null): MetDataLoadStatus
    {
        $status = $estadoCarga instanceof MetDataLoadStatus
            ? $estadoCarga
            : (MetDataLoadStatus::tryFrom((string) $estadoCarga) ?? MetDataLoadStatus::SinDatos);

        return $this->storedMessageIsCalculationError($storedMessage)
            ? MetDataLoadStatus::ErrorCalculo
            : $status;
    }

    public function storedMessageIsCalculationError(?string $storedMessage): bool
    {
        return filled($storedMessage) && ! MetResult::storedErrorMessageIsNonCalculationAlert($storedMessage);
    }

    public function cumplimientoVisible(
        ?MetIndicator $indicator,
        ?float $resultado,
        float $metaEfectiva,
        MetDataLoadStatus $loadStatus,
        bool $hasTrackableLoadFields,
    ): ?float {
        if ($resultado === null || $metaEfectiva <= 0) {
            return null;
        }

        if ($this->sinDatos($resultado, $loadStatus, $hasTrackableLoadFields)) {
            return null;
        }

        return $this->calculationService->indicatorCompliancePercent($indicator, $resultado, $metaEfectiva);
    }
}
