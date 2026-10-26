<?php

namespace App\Services\Notifications;

use App\Models\CenHieDependency;
use App\Models\ComIndicatorSetting;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\Year;

final class ReferenteAssignmentMailDataFactory
{
    public static function forMetasIndicator(
        MetIndicator $indicator,
        IndicatorType $instrument,
        CenHieDependency $dependency,
        Year $year,
    ): ReferenteAssignmentMailData {
        $routeSlug = (string) $instrument->route;

        return new ReferenteAssignmentMailData(
            moduleLabel: 'Metas',
            indicatorClave: (string) $indicator->clave,
            indicatorNombre: (string) $indicator->nombre,
            contextLabel: (string) $instrument->nombre_indicador,
            servicioSaludNombre: (string) $dependency->nombre,
            ano: (int) $year->ano,
            showUrl: route('metas.instrument.indicators.show', [
                'indicatorType' => $routeSlug,
                'indicator' => $indicator,
            ]),
            cargaUrl: route('metas.instrument.indicador.carga', [
                'indicatorType' => $routeSlug,
                'indicator' => $indicator,
            ]),
        );
    }

    public static function forComgesSetting(ComIndicatorSetting $setting, Year $year): ReferenteAssignmentMailData
    {
        $setting->loadMissing([
            'comIndicator.comCommitment',
            'cenHieDependency',
        ]);

        $indicator = $setting->comIndicator;
        $commitment = $indicator?->comCommitment;
        $dependency = $setting->cenHieDependency;

        return new ReferenteAssignmentMailData(
            moduleLabel: 'COMGES',
            indicatorClave: (string) ($indicator?->clave ?? ''),
            indicatorNombre: (string) ($indicator?->nombre ?? ''),
            contextLabel: (string) ($commitment?->nombre ?? 'COMGES'),
            servicioSaludNombre: (string) ($dependency?->nombre ?? ''),
            ano: (int) $year->ano,
            showUrl: $indicator !== null
                ? route('voyager.indicadores-comges.show', $indicator->getKey())
                : route('voyager.indicadores-comges.index'),
            cargaUrl: null,
        );
    }
}
