@php
    $isServerSide = false;
    $showCheckboxColumn = false;
@endphp
{{-- <div class="page-content browse container-fluid">
    @include('voyager::alerts')
    <div class="row">
        <div class="col-md-12"> --}}
@if ($dataTypeContent->count() > 0)
    <div class="panel panel-hidden">
        <div class="panel-body">
            @if ($isServerSide)
                <form method="get" class="form-search">
                    <div id="search-input">
                        <div class="col-2">
                            <select id="search_key" name="key">
                                @foreach ($searchNames as $key => $name)
                                    <option value="{{ $key }}"
                                        @if ($search->key == $key || (empty($search->key) && $key == $defaultSearchKey)) selected @endif>{{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-2">
                            <select id="filter" name="filter">
                                <option value="contains" @if ($search->filter == 'contains') selected @endif>
                                    {{ __('voyager::generic.contains') }}</option>
                                <option value="equals" @if ($search->filter == 'equals') selected @endif>=
                                </option>
                            </select>
                        </div>
                        <div class="input-group col-md-12">
                            <input type="text" class="form-control" placeholder="{{ __('voyager::generic.search') }}"
                                name="s" value="{{ $search->value }}">
                            <span class="input-group-btn">
                                <button class="btn btn-info btn-lg" type="submit">
                                    <i class="voyager-search"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    @if (Request::has('sort_order') && Request::has('order_by'))
                        <input type="hidden" name="sort_order" value="{{ Request::get('sort_order') }}">
                        <input type="hidden" name="order_by" value="{{ Request::get('order_by') }}">
                    @endif
                </form>
            @endif
            <div class="table-responsive">
                <table id="dataTable" class="table table-hover">
                    <thead>
                        <tr>
                            @if ($showCheckboxColumn)
                                <th class="dt-not-orderable">
                                    <input type="checkbox" class="select_all">
                                </th>
                            @endif
                            @foreach ($dataType->browseRows as $row)
                                <th>
                                    @if ($isServerSide && in_array($row->field, $sortableColumns))
                                        <a href="{{ $row->sortByUrl($orderBy, $sortOrder) }}">
                                    @endif
                                    {{ $row->getTranslatedAttribute('display_name') }}
                                    @if ($isServerSide)
                                        @if ($row->isCurrentSortField($orderBy))
                                            @if ($sortOrder == 'asc')
                                                <i class="voyager-angle-up pull-right"></i>
                                            @else
                                                <i class="voyager-angle-down pull-right"></i>
                                            @endif
                                        @endif
                                        </a>
                                    @endif
                                </th>
                            @endforeach
                            <th class="actions text-right dt-not-orderable">{{ __('voyager::generic.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dataTypeContent as $data)
                            <tr>
                                @if ($showCheckboxColumn)
                                    <td>
                                        <input type="checkbox" name="row_id" id="checkbox_{{ $data->getKey() }}"
                                            value="{{ $data->getKey() }}">
                                    </td>
                                @endif
                                @foreach ($dataType->browseRows as $row)
                                    @php
                                        if ($data->{$row->field . '_browse'}) {
                                            $data->{$row->field} = $data->{$row->field . '_browse'};
                                        }
                                    @endphp
                                    <td>
                                        @if (isset($row->details->view_browse))
                                            @include($row->details->view_browse, [
                                                'row' => $row,
                                                'dataType' => $dataType,
                                                'dataTypeContent' => $dataTypeContent,
                                                'content' => $data->{$row->field},
                                                'view' => 'browse',
                                                'options' => $row->details,
                                            ])
                                        @elseif (isset($row->details->view))
                                            @include($row->details->view, [
                                                'row' => $row,
                                                'dataType' => $dataType,
                                                'dataTypeContent' => $dataTypeContent,
                                                'content' => $data->{$row->field},
                                                'action' => 'browse',
                                                'view' => 'browse',
                                                'options' => $row->details,
                                            ])
                                        @elseif($row->type == 'image')
                                            <img src="@if (!filter_var($data->{$row->field}, FILTER_VALIDATE_URL)) {{ Voyager::image($data->{$row->field}) }}@else{{ $data->{$row->field} }} @endif"
                                                style="width:100px">
                                        @elseif($row->type == 'relationship')
                                            @include('voyager::formfields.relationship', [
                                                'view' => 'browse',
                                                'options' => $row->details,
                                            ])
                                        @elseif($row->type == 'select_multiple')
                                            @if (property_exists($row->details, 'relationship'))
                                                @foreach ($data->{$row->field} as $item)
                                                    {{ $item->{$row->field} }}
                                                @endforeach
                                            @elseif(property_exists($row->details, 'options'))
                                                @if (!empty(json_decode($data->{$row->field})))
                                                    @foreach (json_decode($data->{$row->field}) as $item)
                                                        @if (@$row->details->options->{$item})
                                                            {{ $row->details->options->{$item} . (!$loop->last ? ', ' : '') }}
                                                        @endif
                                                    @endforeach
                                                @else
                                                    {{ __('voyager::generic.none') }}
                                                @endif
                                            @endif
                                        @elseif($row->type == 'multiple_checkbox' && property_exists($row->details, 'options'))
                                            @if (@count(json_decode($data->{$row->field}, true)) > 0)
                                                @foreach (json_decode($data->{$row->field}) as $item)
                                                    @if (@$row->details->options->{$item})
                                                        {{ $row->details->options->{$item} . (!$loop->last ? ', ' : '') }}
                                                    @endif
                                                @endforeach
                                            @else
                                                {{ __('voyager::generic.none') }}
                                            @endif
                                        @elseif(($row->type == 'select_dropdown' || $row->type == 'radio_btn') && property_exists($row->details, 'options'))
                                            {!! $row->details->options->{$data->{$row->field}} ?? '' !!}
                                        @elseif($row->type == 'date' || $row->type == 'timestamp')
                                            @if (property_exists($row->details, 'format') && !is_null($data->{$row->field}))
                                                {{ \Carbon\Carbon::parse($data->{$row->field})->formatLocalized($row->details->format) }}
                                            @else
                                                {{ $data->{$row->field} }}
                                            @endif
                                        @elseif($row->type == 'checkbox')
                                            @if (property_exists($row->details, 'on') && property_exists($row->details, 'off'))
                                                @if ($data->{$row->field})
                                                    <span class="label label-info">{{ $row->details->on }}</span>
                                                @else
                                                    <span class="label label-primary">{{ $row->details->off }}</span>
                                                @endif
                                            @else
                                                {{ $data->{$row->field} }}
                                            @endif
                                        @elseif($row->type == 'color')
                                            <span class="badge badge-lg"
                                                style="background-color: {{ $data->{$row->field} }}">{{ $data->{$row->field} }}</span>
                                        @elseif($row->type == 'text')
                                            @include('voyager::multilingual.input-hidden-bread-browse')
                                            <div width="{{ $row->details->options->browseWidth ?? '' }}">
                                                {{ mb_strlen($data->{$row->field}) > 300 ? mb_substr($data->{$row->field}, 0, 300) . ' ...' : $data->{$row->field} }}
                                            </div>
                                        @elseif($row->type == 'text_area')
                                            @include('voyager::multilingual.input-hidden-bread-browse')
                                            <div>
                                                {{ $data->{$row->field} }}
                                            </div>
                                            {{-- <div>
                                                {{ mb_strlen($data->{$row->field}) > 200 ? mb_substr($data->{$row->field}, 0, 200) . ' ...' : $data->{$row->field} }}
                                            </div> --}}
                                        @elseif($row->type == 'file' && !empty($data->{$row->field}))
                                            @include('voyager::multilingual.input-hidden-bread-browse')
                                            @if (json_decode($data->{$row->field}) !== null)
                                                @foreach (json_decode($data->{$row->field}) as $file_index => $file)
                                                    @php
                                                        $orig = is_string($file->original_name ?? null)
                                                            ? $file->original_name
                                                            : '';
                                                        $isPdf = str_ends_with(strtolower($orig), '.pdf');
                                                    @endphp
                                                    <div style="margin-bottom:6px">
                                                        @if ($download)
                                                            <a href="{{ route('download-file', ['slug' => $dataType->slug, 'field' => $row->field, 'id' => $data->id, 'file_index' => $file_index]) }}"
                                                                target="_blank">{{ $orig ?: __('voyager::generic.download') }}</a>
                                                        @else
                                                            {{ $orig ?: '' }}
                                                        @endif
                                                        @if ($download && $isPdf)
                                                            &nbsp;|&nbsp;
                                                            @include(
                                                                'partials.documents.pdf-preview-link',
                                                                [
                                                                    'url' => route('view-document-file', [
                                                                        'slug' => $dataType->slug,
                                                                        'field' => $row->field,
                                                                        'id' => $data->id,
                                                                        'file_index' => $file_index,
                                                                    ]),
                                                                    'filename' => $orig,
                                                                ]
                                                            )
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif
                                        @elseif($row->type == 'rich_text_box')
                                            @include('voyager::multilingual.input-hidden-bread-browse')
                                            <div>
                                                {{ mb_strlen(strip_tags($data->{$row->field}, '<b><i><u>')) > 200 ? mb_substr(strip_tags($data->{$row->field}, '<b><i><u>'), 0, 200) . ' ...' : strip_tags($data->{$row->field}, '<b><i><u>') }}
                                            </div>
                                        @elseif($row->type == 'coordinates')
                                            @include('voyager::partials.coordinates-static-image')
                                        @elseif($row->type == 'multiple_images')
                                            @php $images = json_decode($data->{$row->field}); @endphp
                                            @if ($images)
                                                @php $images = array_slice($images, 0, 3); @endphp
                                                @foreach ($images as $image)
                                                    <img src="@if (!filter_var($image, FILTER_VALIDATE_URL)) {{ Voyager::image($image) }}@else{{ $image }} @endif"
                                                        style="width:50px">
                                                @endforeach
                                            @endif
                                        @elseif($row->type == 'media_picker')
                                            @php
                                                if (is_array($data->{$row->field})) {
                                                    $files = $data->{$row->field};
                                                } else {
                                                    $files = json_decode($data->{$row->field});
                                                }
                                            @endphp
                                            @if ($files)
                                                @if (property_exists($row->details, 'show_as_images') && $row->details->show_as_images)
                                                    @foreach (array_slice($files, 0, 3) as $file)
                                                        <img src="@if (!filter_var($file, FILTER_VALIDATE_URL)) {{ Voyager::image($file) }}@else{{ $file }} @endif"
                                                            style="width:50px">
                                                    @endforeach
                                                @else
                                                    <ul>
                                                        @foreach (array_slice($files, 0, 3) as $file)
                                                            <li>{{ $file }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                @if (count($files) > 3)
                                                    {{ __('voyager::media.files_more', ['count' => count($files) - 3]) }}
                                                @endif
                                            @elseif (is_array($files) && count($files) == 0)
                                                {{ trans_choice('voyager::media.files', 0) }}
                                            @elseif ($data->{$row->field} != '')
                                                @if (property_exists($row->details, 'show_as_images') && $row->details->show_as_images)
                                                    <img src="@if (!filter_var($data->{$row->field}, FILTER_VALIDATE_URL)) {{ Voyager::image($data->{$row->field}) }}@else{{ $data->{$row->field} }} @endif"
                                                        style="width:50px">
                                                @else
                                                    {{ $data->{$row->field} }}
                                                @endif
                                            @else
                                                {{ trans_choice('voyager::media.files', 0) }}
                                            @endif
                                        @else
                                            @include('voyager::multilingual.input-hidden-bread-browse')
                                            <span>{{ $data->{$row->field} }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                @if ($showButtons)
                                    <td class="no-sort no-click bread-actions">
                                        <x-botones.read-button-component :dataType="$dataType" :id="$data->id"
                                            :documentHubContext="$documentHubContext ?? null" />
                                        <x-botones.edit-button-component :dataType="$dataType" :id="$data->id"
                                            :documentHubContext="$documentHubContext ?? null" />
                                        <x-botones.delete-button-component :table="$table" :id="$data->id"
                                            :documentHubContext="$documentHubContext ?? null" />
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($isServerSide)
                <div class="pull-left">
                    <div role="status" class="show-res" aria-live="polite">
                        {{ trans_choice('voyager::generic.showing_entries', $dataTypeContent->total(), [
                            'from' => $dataTypeContent->firstItem(),
                            'to' => $dataTypeContent->lastItem(),
                            'all' => $dataTypeContent->total(),
                        ]) }}
                    </div>
                </div>
                <div class="pull-right">
                    {{ $dataTypeContent->appends([
                            's' => $search->value,
                            'filter' => $search->filter,
                            'key' => $search->key,
                            'order_by' => $orderBy,
                            'sort_order' => $sortOrder,
                            'showSoftDeleted' => $showSoftDeleted,
                        ])->links() }}
                </div>
            @endif
        </div>
    </div>
@else
    <div class="text-center">
        <br>No existen registros para esta sección<br><br>
    </div>
@endif
@include('partials.documents.pdf-preview-modal')
{{-- </div>
    </div>
</div> --}}
