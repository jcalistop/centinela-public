<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Enums\Periodicidad;
use App\Models\MetIndicator;

class MetIndicatorPeriodicityResolver
{
    public const DEFAULT_PERIODICIDAD = 'ANUAL';

    public function __construct(private MetFieldSsConfigService $fieldSsConfigService) {}

    public function resolveForCarga(MetIndicator $indicator, int $ssId, int $yearId): Periodicidad
    {
        $fields = $indicator->relationLoaded('dataFields')
            ? $indicator->dataFields
            : $indicator->dataFields()->orderBy('orden')->get();

        $numeradorFields = $this->fieldSsConfigService
            ->applyEffectiveTypes($fields, $ssId, $yearId)
            ->where('componente', DataFieldComponente::Numerador);

        if ($numeradorFields->isEmpty()) {
            return Periodicidad::Anual;
        }

        $resolvedPeriodicidad = Periodicidad::Anual;
        $maxGranularity = 0;

        foreach ($numeradorFields as $field) {
            $inputType = $field->input_type;
            $granularity = $inputType->granularity();

            if ($granularity > $maxGranularity) {
                $maxGranularity = $granularity;
                $resolvedPeriodicidad = $this->periodicidadFor($inputType);
            }
        }

        return $resolvedPeriodicidad;
    }

    private function periodicidadFor(DataFieldInputType $inputType): Periodicidad
    {
        return match ($inputType) {
            DataFieldInputType::Mensual => Periodicidad::Mensual,
            DataFieldInputType::Trimestral => Periodicidad::Trimestral,
            DataFieldInputType::Semestral => Periodicidad::Semestral,
            DataFieldInputType::Anual => Periodicidad::Anual,
            DataFieldInputType::LineaBase => Periodicidad::Anual,
        };
    }
}
