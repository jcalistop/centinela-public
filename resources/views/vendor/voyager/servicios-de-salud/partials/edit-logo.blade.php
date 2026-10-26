{{-- Preview + input file para logo SS (disco Voyager private → ruta pública). --}}
@if (isset($dataTypeContent->{$row->field}) && filled($dataTypeContent->{$row->field}))
    <div data-field-name="{{ $row->field }}">
        <a href="#" class="voyager-x remove-single-image" style="position:absolute;"></a>
        <img src="{{ $dataTypeContent->logoPublicUrl() }}"
            data-file-name="{{ $dataTypeContent->{$row->field} }}" data-id="{{ $dataTypeContent->getKey() }}"
            style="max-width:200px; height:auto; clear:both; display:block; padding:2px; border:1px solid #ddd; margin-bottom:10px;">
    </div>
@endif
<input @if ($row->required == 1 && ! isset($dataTypeContent->{$row->field})) required @endif type="file"
    name="{{ $row->field }}" accept="image/*">
