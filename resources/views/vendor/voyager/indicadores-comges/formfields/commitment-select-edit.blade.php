@php
    use App\Models\ComCommitment;
@endphp

@can('edit', $dataTypeContent)
    <select name="{{ $row->field }}" class="form-control" id="{{ $row->field }}">
        @foreach (ComCommitment::query()->currentYear()->orderBy('clave')->get() as $commitment)
            <option value="{{ $commitment->id }}" @selected((int) old($row->field, $dataTypeContent->{$row->field}) === (int) $commitment->id)>
                {{ $commitment->clave }} — {{ $commitment->nombre }}
            </option>
        @endforeach
    </select>
@else
    @include('formfields.read_only', [
        'row' => $row,
        'dataType' => $dataType,
        'dataTypeContent' => $dataTypeContent,
        'options' => $options,
    ])
@endcan
