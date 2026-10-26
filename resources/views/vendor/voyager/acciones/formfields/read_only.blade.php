@if($dataTypeContent->exists)
<h4>{{ $dataTypeContent->comIndicatorCutoff->comIndicator->clave}} / {{ $dataTypeContent->comIndicatorCutoff->cutoff->clave }}</h4>
<input type="hidden" class="form-control" name="{{ $row->field }}" value="{{ old($row->field, $dataTypeContent->{$row->field} ?? $options->default ?? '') }}">
@else
<h4>{{ $comIndicatorCutoff->comIndicator->clave}} / {{ $comIndicatorCutoff->cutoff->clave }}</h4>
<input type="hidden" class="form-control" name="{{ $row->field }}" value="{{ old($row->field, $comIndicatorCutoff->id ?? $options->default ?? '') }}">
@endif