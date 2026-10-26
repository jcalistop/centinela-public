@props([
    'percent' => null,
    'greenMin' => 100.0,
    'yellowMin' => 75.0,
])

@php
    $greenMin = (float) $greenMin;
    $yellowMin = (float) $yellowMin;
    $class = 'nulo';
    $label = 'S/I';

    if ($percent !== null) {
        $numeric = (float) $percent;
        $label = number_format($numeric, 1, ',', '.').'%';

        if ($numeric >= $greenMin) {
            $class = 'green';
        } elseif ($numeric >= $yellowMin) {
            $class = 'orange';
        } elseif ($numeric >= 0) {
            $class = 'red';
        } else {
            $class = 'error';
        }
    }
@endphp

<div {{ $attributes->class(['semaforo', $class]) }}>{{ $label }}</div>
