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
                        @if ($edit)
                            {{ method_field('PUT') }}
                        @endif

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

                            @php
                                $dataTypeRows = $dataType->{$edit ? 'editRows' : 'addRows'};
                            @endphp

                            {{-- Seccion 1: Datos Basicos --}}
                            <legend class="text-center" style="background-color: #f0f0f0;padding: 5px;">Datos Basicos
                            </legend>

                            @foreach ($dataTypeRows as $row)
                                @php
                                    $display_options = $row->details->display ?? null;
                                    if ($dataTypeContent->{$row->field . '_' . ($edit ? 'edit' : 'add')}) {
                                        $dataTypeContent->{$row->field} =
                                            $dataTypeContent->{$row->field . '_' . ($edit ? 'edit' : 'add')};
                                    }
                                @endphp

                                {{-- Skip address fields in standard loop; they are rendered separately below --}}
                                @if (in_array($row->field, ['cen_center_id']))
                                    @continue
                                @endif

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

                            {{-- Seccion 3: Datos de Direccion --}}
                            <div class="clearfix"></div>
                            <legend class="text-center" style="background-color: #f0f0f0;padding: 5px;">Direccion</legend>

                            <div class="form-group col-md-6">
                                <label class="control-label" for="address_region_id">Region</label>
                                <select name="address_region_id" id="address_region_id" class="form-control">
                                    <option value="">-- Seleccione Region --</option>
                                    @foreach ($regions as $region)
                                        <option value="{{ $region->id }}"
                                            @if (($selectedRegionId ?? null) == $region->id) selected @endif>
                                            {{ $region->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label class="control-label" for="address_province_id">Provincia</label>
                                <select name="address_province_id" id="address_province_id" class="form-control">
                                    <option value="">-- Seleccione Provincia --</option>
                                    @if (isset($provinces))
                                        @foreach ($provinces as $province)
                                            <option value="{{ $province->id }}"
                                                @if (($selectedProvinceId ?? null) == $province->id) selected @endif>
                                                {{ $province->nombre }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label class="control-label" for="address_cen_commune_id">Comuna</label>
                                <select name="address_cen_commune_id" id="address_cen_commune_id" class="form-control">
                                    <option value="">-- Seleccione Comuna --</option>
                                    @if (isset($communes))
                                        @foreach ($communes as $commune)
                                            <option value="{{ $commune->id }}"
                                                @if (($selectedCommuneId ?? null) == $commune->id) selected @endif>
                                                {{ $commune->nombre }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label class="control-label" for="address_via">Via</label>
                                <select name="address_via" id="address_via" class="form-control">
                                    <option value="">-- Seleccione --</option>
                                    <option value="AVENIDA" @if (($address->via ?? '') == 'AVENIDA') selected @endif>AVENIDA
                                    </option>
                                    <option value="CALLE" @if (($address->via ?? '') == 'CALLE') selected @endif>CALLE</option>
                                    <option value="CAMINO" @if (($address->via ?? '') == 'CAMINO') selected @endif>CAMINO
                                    </option>
                                    <option value="CARRETERA" @if (($address->via ?? '') == 'CARRETERA') selected @endif>CARRETERA
                                    </option>
                                    <option value="OTRO" @if (($address->via ?? '') == 'OTRO') selected @endif>OTRO</option>
                                    <option value="PASAJE" @if (($address->via ?? '') == 'PASAJE') selected @endif>PASAJE
                                    </option>
                                    <option value="SECTOR" @if (($address->via ?? '') == 'SECTOR') selected @endif>SECTOR
                                    </option>
                                    <option value="PENDIENTE" @if (($address->via ?? '') == 'PENDIENTE') selected @endif>PENDIENTE
                                    </option>
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label class="control-label" for="address_numero">Numero</label>
                                <input type="text" name="address_numero" id="address_numero" class="form-control"
                                    value="{{ $address->numero ?? '' }}">
                            </div>

                            <div class="form-group col-md-6">
                                <label class="control-label" for="address_direccion">Direccion</label>
                                <input type="text" name="address_direccion" id="address_direccion" class="form-control"
                                    value="{{ $address->direccion ?? '' }}">
                            </div>

                            <div class="form-group col-md-4">
                                <label class="control-label" for="address_telefono">Telefono</label>
                                <input type="text" name="address_telefono" id="address_telefono" class="form-control"
                                    value="{{ $address->telefono ?? '' }}">
                            </div>

                            <div class="form-group col-md-4">
                                <label class="control-label" for="address_latitud">Latitud</label>
                                <input type="text" name="address_latitud" id="address_latitud" class="form-control"
                                    value="{{ $address->latitud ?? '' }}">
                            </div>

                            <div class="form-group col-md-4">
                                <label class="control-label" for="address_longitud">Longitud</label>
                                <input type="text" name="address_longitud" id="address_longitud" class="form-control"
                                    value="{{ $address->longitud ?? '' }}">
                            </div>

                        </div><!-- panel-body -->

                        <div class="panel-footer">
                        @section('submit-buttons')
                            <button type="submit"
                                class="btn btn-primary save">{{ __('voyager::generic.save') }}</button>
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
                <h4 class="modal-title"><i class="voyager-warning"></i> {{ __('voyager::generic.are_you_sure') }}
                </h4>
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

        // ---- Cascading dropdowns: Region -> Provincia -> Comuna ----
        var provincesByRegion = {};
        var communesByProvince = {};

        function loadProvinces(regionId, selectedProvinceId) {
            if (!regionId) {
                $('#address_province_id').empty().append(
                '<option value="">-- Seleccione Provincia --</option>');
                $('#address_cen_commune_id').empty().append(
                '<option value="">-- Seleccione Comuna --</option>');
                return;
            }

            $.getJSON('{{ route('voyager.' . $dataType->slug . '.relation') }}', {
                type: 'address_province_relation',
                method: '{{ $add ? 'add' : 'edit' }}',
                page: 1,
                @if (!$add)
                    id: {{ $dataTypeContent->getKey() }},
                @endif
            }, function() {
                // Fallback: load via direct query
            });

            // Load provinces via AJAX from a simpler endpoint - use direct URL
            $.getJSON('/admin/api/provinces-by-region/' + regionId, function(data) {
                var $provSelect = $('#address_province_id');
                $provSelect.empty().append('<option value="">-- Seleccione Provincia --</option>');
                communesByProvince = {};

                $.each(data, function(i, province) {
                    $provSelect.append('<option value="' + province.id + '">' + province
                        .nombre + '</option>');
                });

                if (selectedProvinceId) {
                    $provSelect.val(selectedProvinceId);
                }

                var newProvinceId = $provSelect.val();
                if (newProvinceId) {
                    loadCommunes(newProvinceId, {{ $selectedCommuneId ?? 'null' }});
                }
            });
        }

        function loadCommunes(provinceId, selectedCommuneId) {
            if (!provinceId) {
                $('#address_cen_commune_id').empty().append(
                '<option value="">-- Seleccione Comuna --</option>');
                return;
            }

            $.getJSON('/admin/api/communes-by-province/' + provinceId, function(data) {
                var $commSelect = $('#address_cen_commune_id');
                $commSelect.empty().append('<option value="">-- Seleccione Comuna --</option>');

                $.each(data, function(i, commune) {
                    $commSelect.append('<option value="' + commune.id + '">' + commune.nombre +
                        '</option>');
                });

                if (selectedCommuneId) {
                    $commSelect.val(selectedCommuneId);
                }
            });
        }

        $('#address_region_id').on('change', function() {
            var regionId = $(this).val();
            loadProvinces(regionId, null);
        });

        $('#address_province_id').on('change', function() {
            var provinceId = $(this).val();
            loadCommunes(provinceId, null);
        });

        // On initial load, pre-populate provinces if a region is selected
        @if ($edit && isset($selectedRegionId) && $selectedRegionId)
            var initialRegionId = {{ $selectedRegionId }};
            if (initialRegionId) {
                loadProvinces(initialRegionId, {{ $selectedProvinceId ?? 'null' }});
            }
        @endif
    });
</script>
@include('partials.voyager.bootstrap-modal-stack', ['modalIds' => ['#confirm_delete_modal']])
@stop
