@php
    $code = $content ?? null;
    $label = \App\Models\IndicatorType::measurementGranularityLabel($code);
@endphp
@if ($label === '—')
    <span class="text-muted">—</span>
@else
    <span title="{{ e($code) }}">{{ $label }}</span>
@endif
