@if (isset($dataTypeContent->{$row->field}))
    @if (json_decode($dataTypeContent->{$row->field}) !== null)
        @foreach (json_decode($dataTypeContent->{$row->field}) as $file_index => $file)
            <div data-field-name="{{ $row->field }}">
                <a class="fileType" target="_blank"
                    href="{{ route('download-file', ['slug' => $dataType->slug, 'field' => $row->field, 'id' => $dataTypeContent->id, 'file_index' => $file_index]) }}"
                    data-file-name="{{ $file->original_name }}" data-id="{{ $dataTypeContent->getKey() }}">
                    {{ $file->original_name ?: '' }}
                </a>
                <a href="#" class="voyager-x remove-multi-file"></a>
            </div>
        @endforeach
    @else
        <div data-field-name="{{ $row->field }}">
            <a class="fileType" target="_blank"
                href="{{ Storage::disk(config('voyager.storage.disk'))->url($dataTypeContent->{$row->field}) }}"
                data-file-name="{{ $dataTypeContent->{$row->field} }}" data-id="{{ $dataTypeContent->getKey() }}">
                {{ __('voyager::generic.download') }}
            </a>
            <a href="#" class="voyager-x remove-single-file"></a>
        </div>
    @endif
@endif
<input @if ($row->required == 1 && !isset($dataTypeContent->{$row->field})) required @endif type="file" name="{{ $row->field }}[]"
    multiple="multiple" data-max-upload-bytes="{{ \App\Support\Upload\UploadSizeLimit::maxBytes() }}"
    data-max-upload-label="{{ \App\Support\Upload\UploadSizeLimit::humanLabel() }}">
<x-upload-size-hint />
