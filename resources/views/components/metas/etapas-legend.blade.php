@props([
    'etapaVigente' => null,
])

<ul class="metas-etapas-legend__list">
    @foreach (\App\Enums\IndicatorType\EtapaVigente::ordenadasPorCiclo() as $etapa)
        <li @class([
            'metas-etapas-legend__item--active' => $etapaVigente === $etapa->value,
        ])>
            <strong>{{ $etapa->label() }}:</strong> {{ $etapa->description() }}
        </li>
    @endforeach
</ul>
