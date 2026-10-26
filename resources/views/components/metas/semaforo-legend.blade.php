@props([
    'route' => null,
    'umbralSuperior' => null,
    'umbralInferior' => null,
    'umbralAmarillo' => null,
    'context' => 'global',
])

@php
    $legend = \App\Support\Metas\MetSemaforoThresholds::resolveLegend(
        $context,
        $umbralSuperior !== null ? (float) $umbralSuperior : null,
        $route,
        $umbralInferior !== null ? (float) $umbralInferior : null,
        $umbralAmarillo !== null ? (float) $umbralAmarillo : null,
    );
    $caption = static fn(array $item): string => $item['range'] !== ''
        ? $item['range'] . ' — ' . $item['label']
        : $item['label'];
@endphp

<div class="semaforo-legend-wrap">
    <div class="semaforo-legend" role="note" aria-label="Leyenda de semáforos">
        <span class="semaforo-legend__item">
            <span class="semaforo semaforo-legend__dot green" aria-hidden="true"></span>
            <span>{{ $caption($legend['green']) }}</span>
        </span>
        <span class="semaforo-legend__item">
            <span class="semaforo semaforo-legend__dot orange" aria-hidden="true"></span>
            <span>{{ $caption($legend['yellow']) }}</span>
        </span>
        <span class="semaforo-legend__item">
            <span class="semaforo semaforo-legend__dot red" aria-hidden="true"></span>
            <span>{{ $caption($legend['red']) }}</span>
        </span>
    </div>
    @if ($legend['note'])
        <span class="semaforo-legend__note text-muted pull-right">{{ $legend['note'] }}</span>
    @endif
</div>
