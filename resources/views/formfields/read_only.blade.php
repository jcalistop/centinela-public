@if($row->details->relationship ?? false)
<h4>{{ $dataTypeContent->{$row->details->relationship}->{$row->details->field} }}</h4>
@else
<h4>{{ $dataTypeContent->{$row->field} }}</h4>
@endif
<input type="hidden" class="form-control" name="{{ $row->field }}"
       placeholder="{{ $row->getTranslatedAttribute('display_name') }}"
       {!! isBreadSlugAutoGenerator($options) !!}
       value="{{ old($row->field, $dataTypeContent->{$row->field} ?? $options->default ?? '') }}">