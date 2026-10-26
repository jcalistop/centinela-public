@props([
    'percent' => null,
    'bold' => false,
])

@php
    $html = app(\App\Support\Dashboard\DashboardAmbitoSemaforoPresenter::class)->value(
        $percent !== null ? (float) $percent : null,
    );
@endphp

<span @class([
    'dashboard-ambito-semaforo',
    'dashboard-ambito-semaforo--bold' => $bold,
])>
    {!! $html !!}
</span>
