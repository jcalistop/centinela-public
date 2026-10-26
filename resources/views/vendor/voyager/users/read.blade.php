@extends('voyager::master')

@section('page_title', __('voyager::generic.view') . ' ' . $dataType->getTranslatedAttribute('display_name_singular'))

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i> {{ __('voyager::generic.viewing') }}
        {{ ucfirst($dataType->getTranslatedAttribute('display_name_singular')) }} &nbsp;
        @can('browse', $dataTypeContent)
            <a href="{{ route('voyager.' . $dataType->slug . '.index') }}" class="btn btn-warning">
                <i class="glyphicon glyphicon-list"></i> <span
                    class="hidden-xs hidden-sm">{{ __('voyager::generic.return_to_list') }}</span>
            </a>
        @endcan
    </h1>

    @include('voyager::multilingual.language-selector')
@stop

@section('hide_gradient_bg')
@stop

@section('content')
    <div class="page-content read container-fluid">
        <div class="row">
            <div class="col-md-12">

                <div class="panel panel-bordered" style="padding-bottom:5px;">
                    <!-- form start -->

                    {{-- dump($dataTypeContent) --}}
                    <div
                        style="background-size:cover; background-image: url({{ $adminBackgroundImageUrl }}); background-position: center center;position:absolute; top:0; left:0; width:100%; height:300px;">
                    </div>
                    <div style="height:160px; display:block; width:100%"></div>
                    <div style="position:relative; z-index:9; text-align:center;">
                        <img src="{{ Voyager::image($dataTypeContent->avatar) }}" class="avatar"
                            style="border-radius:50%; width:150px; height:150px; border:5px solid #fff;"
                            alt="{{ $dataTypeContent->name }} avatar">
                        <h4>{{ ucwords($dataTypeContent->name) }}</h4>
                        <div class="user-email text-muted">{{ $dataTypeContent->email }}</div>
                        <p>Rol: {{ $dataTypeContent->role->display_name }}</p>
                        <h4>{{ $dataTypeContent->cenHieDependency->nombre }}</h4>
                        <p> {{ !is_null($dataTypeContent->unidad) ? 'Unidad de trabajo: ' . $dataTypeContent->unidad : '' }}
                        </p>
                        <p> {{ !is_null($dataTypeContent->anexo) ? 'Unidad de trabajo: ' . $dataTypeContent->anexo : '' }}
                        </p>
                        {{-- Se oculta indicator_relationship hasta cargar los datos --}}
                        {{-- <p>
                            @php
                                $options = $dataType->readRows->where('field', 'user_belongstomany_indicator_relationship')->first()->details;
                                $indicators = $dataTypeContent
                                    ->belongsToMany($options->model, $options->pivot_table, $options->foreign_pivot_key ?? null, $options->related_pivot_key ?? null, $options->parent_key ?? null, $options->key)
                                    ->get()
                                    ->map(function ($item, $key) use ($options) {
                                        return $item->{$options->label};
                                    })
                                    ->all();
                            @endphp
                            {!! empty($indicators) ? "<span class='badge'>Sin indicadores asignados</span>" : '' !!}
                            @foreach ($indicators as $indicator)
                                <span class="badge">{{ $indicator }}</span>
                            @endforeach
                        </p> --}}
                        @can('edit', $dataTypeContent)
                            <a href="{{ route('voyager.' . $dataType->slug . '.edit', $dataTypeContent->getKey()) }}"
                                class="btn btn-primary">
                                <i class="glyphicon glyphicon-pencil"></i>
                                {{ __('voyager::generic.edit') }}
                            </a>
                        @endcan
                        @can('delete', $dataTypeContent)
                            @if ($isSoftDeleted)
                                <a href="{{ route('voyager.' . $dataType->slug . '.restore', $dataTypeContent->getKey()) }}"
                                    title="{{ __('voyager::generic.restore') }}" class="btn btn-default restore"
                                    data-id="{{ $dataTypeContent->getKey() }}" id="restore-{{ $dataTypeContent->getKey() }}">
                                    <i class="voyager-trash"></i> <span
                                        class="hidden-xs hidden-sm">{{ __('voyager::generic.restore') }}</span>
                                </a>
                            @else
                                <a href="javascript:;" title="{{ __('voyager::generic.delete') }}"
                                    class="btn btn-danger delete" data-id="{{ $dataTypeContent->getKey() }}"
                                    id="delete-{{ $dataTypeContent->getKey() }}">
                                    <i class="voyager-trash"></i> <span
                                        class="hidden-xs hidden-sm">{{ __('voyager::generic.delete') }}</span>
                                </a>
                            @endif
                        @endcan
                    </div>
                    {{-- @foreach ($dataType->readRows as $row)
                        @php
                            if ($dataTypeContent->{$row->field . '_read'}) {
                                $dataTypeContent->{$row->field} = $dataTypeContent->{$row->field . '_read'};
                            }
                        @endphp
                        <div class="panel-heading" style="border-bottom:0;">
                            <h3 class="panel-title">{{ $row->getTranslatedAttribute('display_name') }}</h3>
                        </div>

                        <div class="panel-body" style="padding-top:0;">
                            @if (isset($row->details->view_read))
                                @include($row->details->view_read, [
                                    'row' => $row,
                                    'dataType' => $dataType,
                                    'dataTypeContent' => $dataTypeContent,
                                    'content' => $dataTypeContent->{$row->field},
                                    'view' => 'read',
                                    'options' => $row->details,
                                ])
                            @elseif (isset($row->details->view))
                                @include($row->details->view, [
                                    'row' => $row,
                                    'dataType' => $dataType,
                                    'dataTypeContent' => $dataTypeContent,
                                    'content' => $dataTypeContent->{$row->field},
                                    'action' => 'read',
                                    'view' => 'read',
                                    'options' => $row->details,
                                ])
                            @elseif($row->type == 'image')
                                <img class="img-responsive"
                                    src="{{ filter_var($dataTypeContent->{$row->field}, FILTER_VALIDATE_URL) ? $dataTypeContent->{$row->field} : Voyager::image($dataTypeContent->{$row->field}) }}">
                            @elseif($row->type == 'multiple_images')
                                @if (json_decode($dataTypeContent->{$row->field}))
                                    @foreach (json_decode($dataTypeContent->{$row->field}) as $file)
                                        <img class="img-responsive"
                                            src="{{ filter_var($file, FILTER_VALIDATE_URL) ? $file : Voyager::image($file) }}">
                                    @endforeach
                                @else
                                    <img class="img-responsive"
                                        src="{{ filter_var($dataTypeContent->{$row->field}, FILTER_VALIDATE_URL) ? $dataTypeContent->{$row->field} : Voyager::image($dataTypeContent->{$row->field}) }}">
                                @endif
                            @elseif($row->type == 'relationship')
                                @include('voyager::formfields.relationship', [
                                    'view' => 'read',
                                    'options' => $row->details,
                                ])
                            @elseif(
                                $row->type == 'select_dropdown' &&
                                    property_exists($row->details, 'options') &&
                                    !empty($row->details->options->{$dataTypeContent->{$row->field}}))
                                <?php //echo $row->details->options->{$dataTypeContent->{$row->field}};
                                ?>
                            @elseif($row->type == 'select_multiple')
                                @if (property_exists($row->details, 'relationship'))
                                    @foreach (json_decode($dataTypeContent->{$row->field}) as $item)
                                        {{ $item->{$row->field} }}
                                    @endforeach
                                @elseif(property_exists($row->details, 'options'))
                                    @if (!empty(json_decode($dataTypeContent->{$row->field})))
                                        @foreach (json_decode($dataTypeContent->{$row->field}) as $item)
                                            @if (@$row->details->options->{$item})
                                                {{ $row->details->options->{$item} . (!$loop->last ? ', ' : '') }}
                                            @endif
                                        @endforeach
                                    @else
                                        {{ __('voyager::generic.none') }}
                                    @endif
                                @endif
                            @elseif($row->type == 'date' || $row->type == 'timestamp')
                                @if (property_exists($row->details, 'format') && !is_null($dataTypeContent->{$row->field}))
                                    {{ \Carbon\Carbon::parse($dataTypeContent->{$row->field})->formatLocalized($row->details->format) }}
                                @else
                                    {{ $dataTypeContent->{$row->field} }}
                                @endif
                            @elseif($row->type == 'checkbox')
                                @if (property_exists($row->details, 'on') && property_exists($row->details, 'off'))
                                    @if ($dataTypeContent->{$row->field})
                                        <span class="label label-info">{{ $row->details->on }}</span>
                                    @else
                                        <span class="label label-primary">{{ $row->details->off }}</span>
                                    @endif
                                @else
                                    {{ $dataTypeContent->{$row->field} }}
                                @endif
                            @elseif($row->type == 'color')
                                <span class="badge badge-lg"
                                    style="background-color: {{ $dataTypeContent->{$row->field} }}">{{ $dataTypeContent->{$row->field} }}</span>
                            @elseif($row->type == 'coordinates')
                                @include('voyager::partials.coordinates')
                            @elseif($row->type == 'rich_text_box')
                                @include('voyager::multilingual.input-hidden-bread-read')
                                {!! $dataTypeContent->{$row->field} !!}
                            @elseif($row->type == 'file')
                                @if (json_decode($dataTypeContent->{$row->field}))
                                    @foreach (json_decode($dataTypeContent->{$row->field}) as $file)
                                        <a
                                            href="{{ Storage::disk(config('voyager.storage.disk'))->url($file->download_link) ?: '' }}">
                                            {{ $file->original_name ?: '' }}
                                        </a>
                                        <br />
                                    @endforeach
                                @elseif($dataTypeContent->{$row->field})
                                    <a href="{{ Storage::disk(config('voyager.storage.disk'))->url($row->field) ?: '' }}">
                                        {{ __('voyager::generic.download') }}
                                    </a>
                                @endif
                            @else
                                @include('voyager::multilingual.input-hidden-bread-read')
                                <p>{{ $dataTypeContent->{$row->field} }}</p>
                            @endif
                        </div><!-- panel-body -->
                        @if (!$loop->last)
                            <hr style="margin:0;">
                        @endif
                    @endforeach --}}

                </div>
            </div>
        </div>

        <div class="row" style="margin-top: 1rem;">
            <div class="col-md-12">
                @include('voyager::users.partials.referent-indicators')
            </div>
        </div>
    </div>

    {{-- Single delete modal --}}
    <div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"
                        aria-label="{{ __('voyager::generic.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-trash"></i> {{ __('voyager::generic.delete_question') }}
                        {{ strtolower($dataType->getTranslatedAttribute('display_name_singular')) }}?</h4>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('voyager.' . $dataType->slug . '.index') }}" id="delete_form" method="POST">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm"
                            value="{{ __('voyager::generic.delete_confirm') }} {{ strtolower($dataType->getTranslatedAttribute('display_name_singular')) }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right"
                        data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@stop

@section('javascript')
    @if ($isModelTranslatable)
        <script>
            $(document).ready(function() {
                $('.side-body').multilingual();
            });
        </script>
    @endif
    <script>
        var deleteFormAction;
        $('.delete').on('click', function(e) {
            var form = $('#delete_form')[0];

            if (!deleteFormAction) {
                // Save form action initial value
                deleteFormAction = form.action;
            }

            form.action = deleteFormAction.match(/\/[0-9]+$/) ?
                deleteFormAction.replace(/([0-9]+$)/, $(this).data('id')) :
                deleteFormAction + '/' + $(this).data('id');

            $('#delete_modal').modal('show');
        });
    </script>
    @include('partials.voyager.bootstrap-modal-stack', ['modalIds' => ['#delete_modal']])
@stop
