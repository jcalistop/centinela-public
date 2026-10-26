@extends('voyager::master')

@section('css')
    @include('centinela.support.partials.compass-shell')
    <style>
        .voyager .support-compass.cen-compass-ui .receiver-user-search-muted.select2-container--default .select2-selection--single {
            opacity: .45;
            pointer-events: none;
        }
    </style>
@stop

@section('page_title', ($receiver->exists ? 'Editar receptor' : 'Nuevo receptor') . ' &middot; Soporte')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-person"></i>
        {{ $receiver->exists ? 'Editar receptor' : 'Nuevo receptor' }}
    </h1>
@stop

@section('content')
    <div class="page-content compass container-fluid support-compass cen-compass-ui support-module">
        @include('voyager::alerts')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="m-b-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="m-b-15">
            @include('centinela.support.partials.back-link', [
                'href' => route('centinela.support.receivers.index'),
                'label' => 'Receptores',
            ])
        </p>

        <x-centinela.support.compass-block :title="$receiver->exists ? 'Editar receptor' : 'Nuevo receptor'" icon="voyager-person">
            {{-- Stepper visual --}}
            <div class="support-stepper">
                <div class="support-stepper-step support-stepper-step--done" id="step_1_label">
                    Paso 1: Ambito
                </div>
                <div class="support-stepper-step" id="step_2_label">
                    Paso 2: Servicio de Salud
                </div>
                <div class="support-stepper-step" id="step_3_label">
                    Paso 3: Usuario
                </div>
            </div>

            @if ($receiver->exists)
                <form method="post" action="{{ route('centinela.support.receivers.update', $receiver) }}"
                    id="receiver_form">
                    @csrf
                    @method('PUT')
                @else
                    <form method="post" action="{{ route('centinela.support.receivers.store') }}" id="receiver_form">
                        @csrf
            @endif

            <div class="row support-form-row-2col">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label" for="scope">Ambito</label>
                        <select name="scope" id="scope" class="form-control" required>
                            <option value="global" @selected(old('scope', $receiver->scope) === 'global')>
                                Soporte tecnico (alcance global)
                            </option>
                            <option value="ss" @selected(old('scope', $receiver->scope) === 'ss')>
                                Gestion (un Servicio de Salud)
                            </option>
                        </select>
                        <p class="help-block small m-t-10">El ambito determina el area del ticket y que usuarios puedes
                            seleccionar.</p>
                    </div>
                    <div id="wrap_ss">
                        <div class="form-group">
                            <label class="control-label" for="cen_hie_dependency_id">Servicio de Salud</label>
                            <select name="cen_hie_dependency_id" id="cen_hie_dependency_id" class="form-control">
                                <option value="">—</option>
                                @foreach ($serviciosSalud as $ss)
                                    <option value="{{ $ss->id }}" @selected(old('cen_hie_dependency_id', $receiver->cen_hie_dependency_id) == $ss->id)>{{ $ss->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($serviciosSalud->isEmpty())
                                <p class="help-block text-warning small m-t-10">No hay Servicios de Salud habilitados.
                                @else
                                <p class="help-block small m-t-10">Solo se listan SS habilitados.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group" id="wrap_receiver_user">
                        <label class="control-label" for="user_id">Usuario</label>
                        <select name="user_id" id="user_id" class="form-control select2 receivers-user-search" required
                            data-search-url="{{ route('centinela.support.receivers.users.search') }}" style="width:100%">
                            @if ($selectedUser)
                                @php
                                    $roleLbl =
                                        $selectedUser->role?->display_name ?? ($selectedUser->role?->name ?? '—');
                                @endphp
                                <option value="{{ $selectedUser->id }}" selected>
                                    {{ $selectedUser->name }} ({{ $roleLbl }}) &middot;
                                    {{ $selectedUser->email }}
                                </option>
                            @endif
                        </select>
                        <p id="receiver-user-help" class="help-block small m-t-10"></p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group m-b-0">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $receiver->is_primary))>
                                <span class="support-star-primary" style="font-size:0.9rem;">&starf;</span>
                                Receptor primario (asignacion inicial)
                            </label>
                        </div>
                        <div class="checkbox m-b-0">
                            <label>
                                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $receiver->exists ? $receiver->enabled : true))>
                                Habilitado
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary" id="receiver_submit_btn">
                        <i class="voyager-check" aria-hidden="true"></i> Guardar
                    </button>
                </div>
            </div>
            </form>
        </x-centinela.support.compass-block>
    </div>
@stop

@section('javascript')
    <script>
        $(function() {
            var scopeEl = $('#scope');
            var wrapSs = $('#wrap_ss');
            var depSel = $('#cen_hie_dependency_id');
            var $userSel = $('#user_id');
            var userHelpEl = $('#receiver-user-help');
            var searchUrl = $userSel.data('search-url');

            var step1 = $('#step_1_label');
            var step2 = $('#step_2_label');
            var step3 = $('#step_3_label');

            function updateStepper() {
                var isSs = scopeEl.val() === 'ss';

                // Paso 1 siempre activo
                step1.addClass('support-stepper-step--done');

                // Paso 2: activo solo si gestion
                if (isSs) {
                    step2.addClass('support-stepper-step--active');
                } else {
                    step2.removeClass('support-stepper-step--active');
                }

                // Paso 3: activo si (global) o (gestion + SS seleccionado)
                var canStep3 = !isSs || (isSs && depSel.val() && depSel.val() !== '');
                if (canStep3) {
                    step3.addClass('support-stepper-step--active');
                } else {
                    step3.removeClass('support-stepper-step--active');
                }
            }

            function toggleServicioSaludWrap() {
                var isSs = scopeEl.val() === 'ss';
                wrapSs.toggle(isSs);
                if (!isSs) {
                    depSel.val('');
                }
                updateStepper();
            }

            function clearUserChoice() {
                $userSel.empty();
                $userSel.val(null).trigger('change');
            }

            function syncReceiverUserHelp() {
                var isSs = scopeEl.val() === 'ss';
                if (!isSs) {
                    userHelpEl.text(
                        'Busca usuarios con rol administrador (cualquier SS). Minimo 2 caracteres en nombre o correo.'
                    );
                    return;
                }
                if (!depSel.val()) {
                    userHelpEl.text(
                        'Primero selecciona el Servicio de Salud. Luego podras buscar usuarios control-gestion de ese SS.'
                    );
                } else {
                    userHelpEl.text(
                        'Busca usuarios con rol control-gestion del SS elegido. Minimo 2 caracteres.'
                    );
                }
            }

            function receiverUserPickerBlocked() {
                var isSs = scopeEl.val() === 'ss';
                return isSs && (!depSel.val() || depSel.val() === '');
            }

            function syncReceiverUserPickerEnabledState() {
                if (receiverUserPickerBlocked()) {
                    clearUserChoice();
                    var sibling = $('#wrap_receiver_user .select2');
                    sibling.addClass('receiver-user-search-muted');
                } else {
                    var sibling = $('#wrap_receiver_user .select2');
                    sibling.removeClass('receiver-user-search-muted');
                }
                syncReceiverUserHelp();
                updateStepper();
            }

            function initReceiverUserSelect2() {
                if ($userSel.data('select2')) {
                    $userSel.select2('destroy');
                }
                $userSel.select2({
                    width: '100%',
                    placeholder: 'Buscar usuario por nombre o correo…',
                    allowClear: false,
                    minimumInputLength: 2,
                    language: {
                        inputTooShort: function() {
                            return 'Ingresa al menos 2 caracteres para buscar.';
                        },
                        searching: function() {
                            return 'Buscando…';
                        },
                        noResults: function() {
                            return 'Sin resultados.';
                        },
                    },
                    ajax: {
                        url: searchUrl,
                        delay: 250,
                        dataType: 'json',
                        data: function(params) {
                            var scope = scopeEl.val();
                            var payload = {
                                q: params.term,
                                page: params.page || 1,
                                scope: scope,
                            };
                            if (scope === 'ss') {
                                payload.cen_hie_dependency_id = depSel.val() || '';
                            }
                            return payload;
                        },
                        processResults: function(data) {
                            var more = !!(data.pagination && data.pagination.more);
                            return {
                                results: data.results || [],
                                pagination: {
                                    more: more,
                                },
                            };
                        },
                    },
                });
                syncReceiverUserPickerEnabledState();
            }

            scopeEl.on('change', function() {
                toggleServicioSaludWrap();
                clearUserChoice();
                initReceiverUserSelect2();
                syncReceiverUserPickerEnabledState();
            });

            depSel.on('change', function() {
                clearUserChoice();
                initReceiverUserSelect2();
                syncReceiverUserPickerEnabledState();
            });

            toggleServicioSaludWrap();
            initReceiverUserSelect2();
            syncReceiverUserPickerEnabledState();

            // Boton submit con estado de carga
            var receiverForm = document.getElementById('receiver_form');
            var receiverSubmitBtn = document.getElementById('receiver_submit_btn');

            if (receiverForm && receiverSubmitBtn) {
                receiverForm.addEventListener('submit', function() {
                    receiverSubmitBtn.disabled = true;
                    receiverSubmitBtn.innerHTML =
                        '<i class="voyager-refresh" aria-hidden="true"></i> Guardando…';
                });
            }
        });
    </script>
@stop
