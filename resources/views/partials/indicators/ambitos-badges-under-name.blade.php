@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\IndicatorAmbito>|\App\Models\IndicatorAmbito[]|null $ambitos */
    $ambitos = $ambitos ?? collect();
@endphp
@if (auth()->user()
        ?->hasRole(['parametrizador', 'admin']))
    @include('partials.indicators.ambitos-badges', [
        'ambitos' => $ambitos,
        'linkToDetail' => true,
        'showLabel' => true,
    ])
@endif
