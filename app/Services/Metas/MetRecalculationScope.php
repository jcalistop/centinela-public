<?php

namespace App\Services\Metas;

readonly class MetRecalculationScope
{
    public function __construct(
        public int $yearId,
        public int $dependencyId,
        public int $indicatorTypeId,
        public int $centerId,
        public int $metIndicatorId,
        public ?int $measurementUnitId = null,
    ) {}

    public function fingerprint(): string
    {
        return sprintf(
            'calc:scope:%d:%d:%d:%d:%d:%d:AUTOEVALUACION',
            $this->yearId,
            $this->dependencyId,
            $this->indicatorTypeId,
            $this->centerId,
            $this->metIndicatorId,
            (int) ($this->measurementUnitId ?? 0),
        );
    }
}
