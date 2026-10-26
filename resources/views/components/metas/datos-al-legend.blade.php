<div class="metas-datos-al-legend-wrap">
    <div class="semaforo-legend metas-datos-al-legend" role="note" aria-label="Leyenda columna Datos al">
        @foreach (\App\Enums\Metas\MetDataLoadStatus::cases() as $status)
            <span class="semaforo-legend__item">
                <span class="label {{ $status->labelClass() }} metas-datos-al-legend__sample" aria-hidden="true">
                    @if ($status === \App\Enums\Metas\MetDataLoadStatus::SinDatos)
                        Sin datos
                    @else
                        Mes
                    @endif
                </span>
                <span>{{ $status->legendDescription() }}</span>
            </span>
        @endforeach
    </div>
</div>
