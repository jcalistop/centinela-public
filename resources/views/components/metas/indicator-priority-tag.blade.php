@props([
    'priority',
])

@php
    $priorityEnum = $priority instanceof \App\Enums\Metas\MetIndicatorPriority
        ? $priority
        : \App\Enums\Metas\MetIndicatorPriority::tryFrom((string) $priority);
@endphp

@if ($priorityEnum?->showsTag())
    <span {{ $attributes->class(['label', $priorityEnum->labelClass(), 'metas-indicator-priority-tag']) }}
        style="margin-left: 0.35rem; vertical-align: middle;">
        {{ $priorityEnum->label() }}
    </span>
@endif
