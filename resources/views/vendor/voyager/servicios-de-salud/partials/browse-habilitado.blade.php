@php
    $enabled = filter_var($data->habilitado ?? false, FILTER_VALIDATE_BOOLEAN);
@endphp

@if ($enabled)
    <span class="label label-success">{{ $row->details->on ?? 'Sí' }}</span>
@else
    <span class="label label-default">{{ $row->details->off ?? 'No' }}</span>
@endif
