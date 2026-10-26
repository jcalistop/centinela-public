@props([
    'status' => [],
])

@php
    $kind = $status['kind'] ?? 'empty';
    $message = $status['message'] ?? null;
@endphp

<div {{ $attributes->class(['metas-period-row-status']) }}>
    @if ($kind === 'draft_incomplete')
        <span class="label label-warning metas-month-draft-badge" title="{{ $message }}">Incompleto</span>
    @elseif ($kind === 'calculation_error')
        <span class="label label-danger metas-month-draft-badge" title="{{ $message }}">Error</span>
    @elseif ($kind === 'persisted_incomplete')
        <span class="label label-warning metas-month-draft-badge" title="{{ $message }}">Incompleto</span>
    @elseif ($kind === 'ready')
        <span class="label label-success metas-month-draft-badge" title="{{ $message }}">Listo</span>
    @else
        <span class="text-muted small">Vacío</span>
    @endif
</div>
