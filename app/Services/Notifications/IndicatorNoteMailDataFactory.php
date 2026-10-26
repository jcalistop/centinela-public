<?php

namespace App\Services\Notifications;

use App\Enums\Indicators\IndicatorModuleType;
use App\Models\CenHieDependency;
use App\Models\ComIndicator;
use App\Models\IndicatorActivity;
use App\Models\MetIndicator;

final class IndicatorNoteMailDataFactory
{
    public static function fromActivity(IndicatorActivity $activity): IndicatorNoteMailData
    {
        $activity->loadMissing('user:id,name');

        return match ($activity->indicator_type) {
            IndicatorModuleType::Metas => self::forMetas($activity),
            IndicatorModuleType::Comges => self::forComges($activity),
        };
    }

    private static function forMetas(IndicatorActivity $activity): IndicatorNoteMailData
    {
        $indicator = MetIndicator::query()
            ->with(['indicatorType', 'year'])
            ->find($activity->indicator_id);

        $dependency = self::resolveDependency($activity);

        $routeSlug = (string) ($indicator?->indicatorType?->route ?? '');
        $bitacoraUrl = $indicator !== null && $routeSlug !== ''
            ? route('metas.instrument.indicators.show', [
                'indicatorType' => $routeSlug,
                'indicator' => $indicator,
            ]).'#tab-bitacora'
            : url('/');

        return self::build($activity, [
            'moduleLabel' => IndicatorModuleType::Metas->label(),
            'indicatorClave' => (string) ($indicator?->clave ?? '—'),
            'indicatorNombre' => (string) ($indicator?->nombre ?? '—'),
            'contextLabel' => (string) ($indicator?->indicatorType?->nombre_indicador ?? '—'),
            'servicioSaludNombre' => (string) ($dependency?->nombre ?? '—'),
            'ano' => (string) ($indicator?->year?->ano ?? '—'),
            'bitacoraUrl' => $bitacoraUrl,
        ]);
    }

    private static function forComges(IndicatorActivity $activity): IndicatorNoteMailData
    {
        $indicator = ComIndicator::query()
            ->with(['comCommitment.year', 'comCommitment.indicatorType'])
            ->find($activity->indicator_id);

        $dependency = self::resolveDependency($activity);
        $commitment = $indicator?->comCommitment;

        $bitacoraUrl = $indicator !== null
            ? route('voyager.indicadores-comges.show', $indicator->getKey()).'#tab-bitacora'
            : route('voyager.indicadores-comges.index');

        return self::build($activity, [
            'moduleLabel' => IndicatorModuleType::Comges->label(),
            'indicatorClave' => (string) ($indicator?->clave ?? '—'),
            'indicatorNombre' => (string) ($indicator?->nombre ?? '—'),
            'contextLabel' => (string) ($commitment?->nombre ?? 'COMGES'),
            'servicioSaludNombre' => (string) ($dependency?->nombre ?? '—'),
            'ano' => (string) ($commitment?->year?->ano ?? '—'),
            'bitacoraUrl' => $bitacoraUrl,
        ]);
    }

    /**
     * @param  array{
     *     moduleLabel: string,
     *     indicatorClave: string,
     *     indicatorNombre: string,
     *     contextLabel: string,
     *     servicioSaludNombre: string,
     *     ano: string,
     *     bitacoraUrl: string,
     * }  $context
     */
    private static function build(IndicatorActivity $activity, array $context): IndicatorNoteMailData
    {
        return new IndicatorNoteMailData(
            moduleLabel: $context['moduleLabel'],
            indicatorClave: $context['indicatorClave'],
            indicatorNombre: $context['indicatorNombre'],
            contextLabel: $context['contextLabel'],
            servicioSaludNombre: $context['servicioSaludNombre'],
            ano: $context['ano'],
            noteText: (string) $activity->description,
            authorName: (string) ($activity->user?->name ?? 'Usuario del sistema'),
            registeredAt: $activity->created_at?->format('d/m/Y H:i') ?? '—',
            bitacoraUrl: $context['bitacoraUrl'],
        );
    }

    private static function resolveDependency(IndicatorActivity $activity): ?CenHieDependency
    {
        $dependencyId = is_array($activity->metadata)
            ? ($activity->metadata['cen_hie_dependency_id'] ?? null)
            : null;

        if ($dependencyId === null) {
            return null;
        }

        return CenHieDependency::query()->find($dependencyId);
    }
}
