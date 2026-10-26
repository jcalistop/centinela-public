<br>
@php
    $checked = false;
    $options = $dataTypeContent->comIndicator->comIndicatorCutoffs;
@endphp

@if (isset($options) && $options->count())
    @foreach ($options as $option)
        @if (isset($dataTypeContent->{$row->field}) || old($row->field))
            @php
                $checkedData = old($row->field, $dataTypeContent->{$row->field});
                $checkedData = is_array($checkedData) ? $checkedData : json_decode($checkedData, true);
                $checked = in_array($option->id, $checkedData);
            @endphp
        @else
            @php $checked = isset($option->checked) && $option->checked ? true : false; @endphp
        @endif
        <input type="checkbox" name="{{ $row->field }}[{{ $option->id }}]" {!! $checked ? 'checked="checked"' : '' !!}
            value="{{ $option->id }}" id="{{ $option->id }}" />
        <label for="{{ $option->id }}">{{ $option->cutoff->nombre_corte }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
    @endforeach
@endif
