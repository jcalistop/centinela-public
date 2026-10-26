@props(['useScoreScale' => false])

@php
    $useScoreScale = filter_var($useScoreScale, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="semaforo-legend" role="note" aria-label="Leyenda de semáforos">
    <span class="semaforo-legend__item">
        <span class="semaforo semaforo-legend__dot green" aria-hidden="true"></span>
        @if ($useScoreScale)
            <span>4 ptos. (100%) — Cumplimiento total</span>
        @else
            <span>100% — Cumplimiento total</span>
        @endif
    </span>
    <span class="semaforo-legend__item">
        <span class="semaforo semaforo-legend__dot orange" aria-hidden="true"></span>
        @if ($useScoreScale)
            <span>3 a 3,99 ptos. (≥75% a &lt;100%) — Cumplimiento parcial</span>
        @else
            <span>≥75% a &lt;100% — Cumplimiento parcial</span>
        @endif
    </span>
    <span class="semaforo-legend__item">
        <span class="semaforo semaforo-legend__dot red" aria-hidden="true"></span>
        @if ($useScoreScale)
            <span>&lt;3 ptos. (&lt;75%) — Cumplimiento insuficiente</span>
        @else
            <span>&lt;75% — Cumplimiento insuficiente</span>
        @endif
    </span>
    <span class="semaforo-legend__item">
        <span class="semaforo semaforo-legend__dot nulo" aria-hidden="true">S/I</span>
        <span>S/I — Sin información</span>
    </span>
</div>
