@php
    $checked = filter_var(old($row->field, $dataTypeContent->habilitado ?? false), FILTER_VALIDATE_BOOLEAN);
    $class = $options->class ?? 'toggleswitch';
@endphp

<br>
<input type="checkbox" name="{{ $row->field }}" class="{{ $class }}" data-on="{{ $options->on ?? 'Sí' }}"
    data-off="{{ $options->off ?? 'No' }}" @checked($checked)>
