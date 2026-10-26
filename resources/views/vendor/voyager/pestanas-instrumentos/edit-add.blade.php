@php
    $edit = !is_null($dataTypeContent->getKey());
    $add = is_null($dataTypeContent->getKey());
@endphp

@extends('voyager::master')

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('page_title', __('voyager::generic.' . ($edit ? 'edit' : 'add')) . ' ' .
    $dataType->getTranslatedAttribute('display_name_singular'))

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i>
        {{ __('voyager::generic.' . ($edit ? 'edit' : 'add')) . ' ' . $dataType->getTranslatedAttribute('display_name_singular') }}
    </h1>
    @include('voyager::multilingual.language-selector')
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        <div class="row">
            <div class="col-md-12">

                <div class="panel panel-bordered">
                    <!-- form start -->
                    <form role="form" class="form-edit-add"
                        action="{{ $edit ? route('voyager.' . $dataType->slug . '.update', $dataTypeContent->getKey()) : route('voyager.' . $dataType->slug . '.store') }}"
                        method="POST" enctype="multipart/form-data">
                        <!-- PUT Method if we are editing -->
                        @if ($edit)
                            {{ method_field('PUT') }}
                        @endif

                        <!-- CSRF TOKEN -->
                        {{ csrf_field() }}

                        <div class="panel-body">

                            @if (count($errors) > 0)
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <!-- Adding / Editing -->
                            @php
                                $dataTypeRows = $dataType->{$edit ? 'editRows' : 'addRows'};
                            @endphp

                            @foreach ($dataTypeRows as $row)
                                <!-- GET THE DISPLAY OPTIONS -->
                                @php
                                    $display_options = $row->details->display ?? null;
                                    if ($dataTypeContent->{$row->field . '_' . ($edit ? 'edit' : 'add')}) {
                                        $dataTypeContent->{$row->field} =
                                            $dataTypeContent->{$row->field . '_' . ($edit ? 'edit' : 'add')};
                                    }
                                @endphp
                                @if (isset($row->details->legend) && isset($row->details->legend->text))
                                    <legend class="text-{{ $row->details->legend->align ?? 'center' }}"
                                        style="background-color: {{ $row->details->legend->bgcolor ?? '#f0f0f0' }};padding: 5px;">
                                        {{ $row->details->legend->text }}</legend>
                                @endif

                                <div class="form-group @if ($row->type == 'hidden') hidden @endif col-md-{{ $display_options->width ?? 12 }} {{ $errors->has($row->field) ? 'has-error' : '' }}"
                                    @if (isset($display_options->id)) {{ "id=$display_options->id" }} @endif>
                                    {{ $row->slugify }}
                                    <label class="control-label"
                                        for="name">{{ $row->getTranslatedAttribute('display_name') }}</label>
                                    @include('voyager::multilingual.input-hidden-bread-edit-add')
                                    @if ($add && isset($row->details->view_add))
                                        @include($row->details->view_add, [
                                            'row' => $row,
                                            'dataType' => $dataType,
                                            'dataTypeContent' => $dataTypeContent,
                                            'content' => $dataTypeContent->{$row->field},
                                            'view' => 'add',
                                            'options' => $row->details,
                                        ])
                                    @elseif ($edit && isset($row->details->view_edit))
                                        @include($row->details->view_edit, [
                                            'row' => $row,
                                            'dataType' => $dataType,
                                            'dataTypeContent' => $dataTypeContent,
                                            'content' => $dataTypeContent->{$row->field},
                                            'view' => 'edit',
                                            'options' => $row->details,
                                        ])
                                    @elseif (isset($row->details->view))
                                        @include($row->details->view, [
                                            'row' => $row,
                                            'dataType' => $dataType,
                                            'dataTypeContent' => $dataTypeContent,
                                            'content' => $dataTypeContent->{$row->field},
                                            'action' => $edit ? 'edit' : 'add',
                                            'view' => $edit ? 'edit' : 'add',
                                            'options' => $row->details,
                                        ])
                                    @elseif ($row->type == 'relationship')
                                        @include('voyager::formfields.relationship', [
                                            'options' => $row->details,
                                        ])
                                    @else
                                        {!! app('voyager')->formField($row, $dataType, $dataTypeContent) !!}
                                    @endif

                                    @foreach (app('voyager')->afterFormFields($row, $dataType, $dataTypeContent) as $after)
                                        {!! $after->handle($row, $dataType, $dataTypeContent) !!}
                                    @endforeach
                                    @if ($errors->has($row->field))
                                        @foreach ($errors->get($row->field) as $error)
                                            <span class="help-block">{{ $error }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach

                        </div><!-- panel-body -->

                        <div class="panel-footer">
                        @section('submit-buttons')
                            <button type="submit" class="btn btn-primary save">{{ __('voyager::generic.save') }}</button>
                        @stop
                        @yield('submit-buttons')
                    </div>
                </form>

                <div style="display:none">
                    <input type="hidden" id="upload_url" value="{{ route('voyager.upload') }}">
                    <input type="hidden" id="upload_type_slug" value="{{ $dataType->slug }}">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-danger" id="confirm_delete_modal">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"><i class="voyager-warning"></i> {{ __('voyager::generic.are_you_sure') }}</h4>
            </div>

            <div class="modal-body">
                <h4>{{ __('voyager::generic.are_you_sure_delete') }} '<span class="confirm_delete_name"></span>'</h4>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                    data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                <button type="button" class="btn btn-danger"
                    id="confirm_delete">{{ __('voyager::generic.delete_confirm') }}</button>
            </div>
        </div>
    </div>
</div>
<!-- End Delete File Modal -->
@stop

@section('javascript')
<script>
    var params = {};
    var $file;

    function deleteHandler(tag, isMulti) {
        return function() {
            $file = $(this).siblings(tag);

            params = {
                slug: '{{ $dataType->slug }}',
                filename: $file.data('file-name'),
                id: $file.data('id'),
                field: $file.parent().data('field-name'),
                multi: isMulti,
                _token: '{{ csrf_token() }}'
            }

            $('.confirm_delete_name').text(params.filename);
            $('#confirm_delete_modal').modal('show');
        };
    }

    $('document').ready(function() {
        $('.toggleswitch').bootstrapToggle();

        //Init datepicker for date fields if data-datepicker attribute defined
        //or if browser does not handle date inputs
        $('.form-group input[type=date]').each(function(idx, elt) {
            if (elt.hasAttribute('data-datepicker')) {
                elt.type = 'text';
                $(elt).datetimepicker($(elt).data('datepicker'));
            } else if (elt.type != 'date') {
                elt.type = 'text';
                $(elt).datetimepicker({
                    format: 'L',
                    extraFormats: ['YYYY-MM-DD']
                }).datetimepicker($(elt).data('datepicker'));
            }
        });

        @if ($isModelTranslatable)
            $('.side-body').multilingual({
                "editing": true
            });
        @endif

        $('.side-body input[data-slug-origin]').each(function(i, el) {
            $(el).slugify();
        });

        $('.form-group').on('click', '.remove-multi-image', deleteHandler('img', true));
        $('.form-group').on('click', '.remove-single-image', deleteHandler('img', false));
        $('.form-group').on('click', '.remove-multi-file', deleteHandler('a', true));
        $('.form-group').on('click', '.remove-single-file', deleteHandler('a', false));

        $('#confirm_delete').on('click', function() {
            $.post('{{ route('voyager.' . $dataType->slug . '.media.remove') }}', params, function(
                response) {
                if (response &&
                    response.data &&
                    response.data.status &&
                    response.data.status == 200) {

                    toastr.success(response.data.message);
                    $file.parent().fadeOut(300, function() {
                        $(this).remove();
                    })
                } else {
                    toastr.error("Error removing file.");
                }
            });

            $('#confirm_delete_modal').modal('hide');
        });
        $('[data-toggle="tooltip"]').tooltip();
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Crear el botón
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-info';
        btn.textContent = 'Cargar texto de ejemplo al campo Data Json';
        btn.onclick = function() {
            var textoEjemplo =
                "{\n    \"Establecimiento A\": {\n        \"Enero\": 647,\n        \"Febrero\": 242,\n        \"Marzo\": 257,\n        \"Abril\": 489,\n        \"Mayo\": 483,\n        \"Junio\": 269,\n        \"Julio\": 591,\n        \"Agosto\": 622,\n        \"Septiembre\": 551,\n        \"Octubre\": 382,\n        \"Noviembre\": 485,\n        \"Diciembre\": 393\n    },\n    \"Establecimiento B\": {\n        \"Enero\": 418,\n        \"Febrero\": 557,\n        \"Marzo\": 541,\n        \"Abril\": 427,\n        \"Mayo\": 328,\n        \"Junio\": 218,\n        \"Julio\": 586,\n        \"Agosto\": 315,\n        \"Septiembre\": 429,\n        \"Octubre\": 472,\n        \"Noviembre\": 639,\n        \"Diciembre\": 649\n    },\n    \"Establecimiento C\": {\n        \"Enero\": 105,\n        \"Febrero\": 169,\n        \"Marzo\": 686,\n        \"Abril\": 285,\n        \"Mayo\": 668,\n        \"Junio\": 486,\n        \"Julio\": 178,\n        \"Agosto\": 680,\n        \"Septiembre\": 348,\n        \"Octubre\": 579,\n        \"Noviembre\": 587,\n        \"Diciembre\": 694\n    },\n    \"Establecimiento D\": {\n        \"Enero\": 572,\n        \"Febrero\": 546,\n        \"Marzo\": 490,\n        \"Abril\": 444,\n        \"Mayo\": 621,\n        \"Junio\": 445,\n        \"Julio\": 381,\n        \"Agosto\": 454,\n        \"Septiembre\": 349,\n        \"Octubre\": 457,\n        \"Noviembre\": 466,\n        \"Diciembre\": 545\n    },\n    \"Establecimiento E\": {\n        \"Enero\": 526,\n        \"Febrero\": 234,\n        \"Marzo\": 204,\n        \"Abril\": 240,\n        \"Mayo\": 321,\n        \"Junio\": 554,\n        \"Julio\": 113,\n        \"Agosto\": 694,\n        \"Septiembre\": 626,\n        \"Octubre\": 612,\n        \"Noviembre\": 237,\n        \"Diciembre\": 482\n    },\n    \"Establecimiento F\": {\n        \"Enero\": 653,\n        \"Febrero\": 640,\n        \"Marzo\": 530,\n        \"Abril\": 491,\n        \"Mayo\": 523,\n        \"Junio\": 637,\n        \"Julio\": 527,\n        \"Agosto\": 586,\n        \"Septiembre\": 635,\n        \"Octubre\": 139,\n        \"Noviembre\": 237,\n        \"Diciembre\": 632\n    }\n}";
            var editor = ace.edit("json-input-data_json");
            if (editor.getValue().trim() !== '') { // Verifica si el editor ya tiene contenido
                var confirmacion = confirm(
                    '¡Atención! Si continúas, el contenido actual del campo "Data Json" será reemplazado.\n ¿Deseas continuar?'
                    );
                if (!confirmacion) {
                    return; // Si el usuario hace clic en "Cancelar", detiene la ejecución
                }
            }
            editor.setValue(textoEjemplo);
            editor.clearSelection();
        };

        // Insertar el botón al lado del div, no como hijo
        var jsonInputDiv = document.getElementById('json-input-data_json');
        if (jsonInputDiv) {
            jsonInputDiv.parentNode.insertBefore(btn, jsonInputDiv.nextSibling);
        }
    });
</script>
@include('partials.voyager.bootstrap-modal-stack', ['modalIds' => ['#confirm_delete_modal']])
@stop
