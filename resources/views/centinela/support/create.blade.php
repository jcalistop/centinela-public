@extends('voyager::master')

@section('css')
    @include('centinela.support.partials.compass-shell')
@stop

@section('page_title', 'Soporte — Nuevo ticket')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-plus"></i> Nuevo ticket de soporte
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
                'href' => route('centinela.support.index'),
                'label' => 'Bandeja Soporte',
            ])
        </p>

        <x-centinela.support.compass-block title="Datos del ticket" icon="voyager-edit">
            <form method="post" action="{{ route('centinela.support.store') }}" class="form-horizontal"
                id="create_ticket_form" enctype="multipart/form-data">
                @csrf

                @if (auth()->user()->hasRole('admin'))
                    <div class="form-group">
                        <label class="control-label" for="cen_hie_dependency_id">Servicio de Salud</label>
                        <select name="cen_hie_dependency_id" id="cen_hie_dependency_id" class="form-control" required>
                            <option value="">Seleccione…</option>
                            @foreach ($serviceDependencies as $ss)
                                <option value="{{ $ss->id }}" @selected(old('cen_hie_dependency_id') == $ss->id)>{{ $ss->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @if ($serviceDependencies->isEmpty())
                            <p class="help-block text-warning small m-t-10">No hay Servicios de Salud habilitados.</p>
                        @else
                            <p class="help-block small m-t-10">Solo se listan Servicios de Salud habilitados.</p>
                        @endif
                    </div>
                @endif

                @if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('control-gestion'))
                    <div class="form-group" id="wrap_requester_user">
                        <label class="control-label" for="requester_user_id">Crear ticket a nombre de</label>
                        <select name="requester_user_id" id="requester_user_id"
                            class="form-control select2 requester-user-search" style="width:100%">
                            <option value=""></option>
                            @if (old('requester_user_id'))
                                <option value="{{ old('requester_user_id') }}" selected>Seleccionado</option>
                            @endif
                        </select>
                        <p class="help-block small m-t-10" id="requester_user_help">
                            Útil para cargar solicitudes recibidas por otras vías, a nombre de quien las solicita.
                            Escribe al menos 2 caracteres (nombre o correo) para buscar.
                        </p>
                    </div>
                @endif

                {{-- Seccion: Informacion del ticket --}}
                <div class="support-form-section">
                    <div class="support-form-section-title">Informacion del ticket</div>

                    <div class="form-group">
                        <label class="control-label" for="area">Area</label>
                        <select name="area" id="area" class="form-control" required>
                            @foreach (\App\Enums\SupportTicketArea::cases() as $area)
                                <option value="{{ $area->value }}" @selected(old('area', \App\Enums\SupportTicketArea::Gestion->value) === $area->value)>
                                    {{ \App\Support\SupportTicket\SupportTicketCatalog::labelForArea($area) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="support-area-guidance help-block small m-t-10 m-b-0" id="area_guidance" role="note">
                            <p class="m-b-5" id="area_guidance_heading"></p>
                            <ul class="m-b-0 support-area-guidance-list" id="area_guidance_items"></ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="subject">Asunto</label>
                        <input type="text" name="subject" id="subject" class="form-control"
                            value="{{ old('subject') }}" required maxlength="255">
                        <div class="support-char-counter" id="subject_counter">0/255</div>
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="body">Descripcion</label>
                        <textarea name="body" id="body" class="form-control" rows="8" required maxlength="5000">{{ old('body') }}</textarea>
                        <div class="support-char-counter" id="body_counter">0/5000</div>
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="attachment">Archivo adjunto (opcional)</label>
                        <input type="file" name="attachment" id="attachment" class="form-control">
                        <x-upload-size-hint class="m-t-5" />
                        <p class="help-block small m-t-5">Solo puedes adjuntar un archivo por ticket. Si necesitas enviar
                            varios, súbelos en un archivo comprimido (ZIP, RAR u otro formato habitual). Pueden ser
                            capturas, documentos u otros archivos que ayuden a describir el problema.</p>
                    </div>
                </div>

                {{-- Seccion: Contexto adicional (opcional) --}}
                <div class="support-form-section support-form-section--optional">
                    <div class="support-form-section-title">Contexto adicional (opcional)</div>

                    <div class="row support-form-row-2col">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label" for="year_id">Ano (opcional)</label>
                                <select name="year_id" id="year_id" class="form-control">
                                    <option value="">—</option>
                                    @foreach ($years as $y)
                                        <option value="{{ $y->id }}" @selected(old('year_id') == $y->id)>
                                            {{ $y->ano }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label" for="indicator_type_id">Instrumento (opcional)</label>
                                <select name="indicator_type_id" id="indicator_type_id" class="form-control"
                                    @disabled(auth()->user()->hasRole('admin') && !old('cen_hie_dependency_id'))>
                                    <option value="">—</option>
                                    @foreach ($indicatorTypes as $it)
                                        <option value="{{ $it->id }}" @selected(old('indicator_type_id') == $it->id)>
                                            {{ $it->nombre_indicador }}</option>
                                    @endforeach
                                </select>
                                @if (auth()->user()->hasRole('admin'))
                                    <p class="help-block small m-t-10" id="indicator_type_help">
                                        Solo se listan instrumentos habilitados para el Servicio de Salud seleccionado
                                        (año de sesión).
                                    </p>
                                @elseif ($indicatorTypes->isEmpty())
                                    <p class="help-block small m-t-10 text-muted">No hay instrumentos habilitados para tu
                                        Servicio de Salud en el año de sesión actual.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="submit_btn">
                    <i class="voyager-plus" aria-hidden="true"></i> Crear ticket
                </button>
            </form>
        </x-centinela.support.compass-block>
    </div>
@stop

@section('javascript')
    <script>
        (function() {
            var areaGuidance = @json($areaGuidance);
            var areaEl = document.getElementById('area');
            var headingEl = document.getElementById('area_guidance_heading');
            var itemsEl = document.getElementById('area_guidance_items');

            function renderAreaGuidance() {
                if (!areaEl || !headingEl || !itemsEl) {
                    return;
                }

                var guidance = areaGuidance[areaEl.value] || null;
                if (!guidance) {
                    headingEl.textContent = '';
                    itemsEl.innerHTML = '';
                    return;
                }

                headingEl.textContent = guidance.heading || '';
                itemsEl.innerHTML = '';
                (guidance.items || []).forEach(function(item) {
                    var li = document.createElement('li');
                    li.textContent = item;
                    itemsEl.appendChild(li);
                });
            }

            if (areaEl) {
                areaEl.addEventListener('change', renderAreaGuidance);
                renderAreaGuidance();
            }

            function setupCharCounter(inputId, counterId) {
                var input = document.getElementById(inputId);
                var counter = document.getElementById(counterId);
                if (!input || !counter) return;
                var max = parseInt(input.getAttribute('maxlength')) || 5000;

                function update() {
                    var len = input.value.length;
                    counter.textContent = len + '/' + max;
                    counter.classList.remove('support-char-counter--warn', 'support-char-counter--danger');
                    if (len > max * 0.9) {
                        counter.classList.add('support-char-counter--danger');
                    } else if (len > max * 0.75) {
                        counter.classList.add('support-char-counter--warn');
                    }
                }
                input.addEventListener('input', update);
                update();
            }
            setupCharCounter('subject', 'subject_counter');
            setupCharCounter('body', 'body_counter');
        })();

        $(function() {
            var $requester = $('#requester_user_id');
            var ssEl = document.getElementById('cen_hie_dependency_id');
            var isAdmin = @json(auth()->user()->hasRole('admin'));
            var requesterHelpEl = document.getElementById('requester_user_help');
            var searchUrl = @json(route('centinela.support.requesters.search'));

            function requesterPickerBlocked() {
                return isAdmin && ssEl && (!ssEl.value || ssEl.value === '');
            }

            function syncRequesterHelp() {
                if (!requesterHelpEl) {
                    return;
                }
                if (requesterPickerBlocked()) {
                    requesterHelpEl.textContent =
                        'Primero selecciona el Servicio de Salud. Luego podrás buscar usuarios de ese SS (mínimo 2 caracteres en nombre o correo).';
                    return;
                }
                requesterHelpEl.textContent =
                    'Opcional. Útil para cargar solicitudes recibidas por otras vías. Escribe al menos 2 caracteres (nombre o correo) para buscar.';
            }

            function syncRequesterPickerState() {
                if (!$requester.length) {
                    return;
                }
                var $container = $('#wrap_requester_user .select2-container');
                if (requesterPickerBlocked()) {
                    $requester.val(null).trigger('change');
                    $container.addClass('requester-user-search-muted');
                } else {
                    $container.removeClass('requester-user-search-muted');
                }
                syncRequesterHelp();
            }

            function initRequesterSelect2() {
                if (!$requester.length || !$.fn.select2) {
                    return;
                }

                if ($requester.data('select2')) {
                    $requester.select2('destroy');
                }

                $requester.select2({
                    width: '100%',
                    placeholder: 'Buscar usuario por nombre o correo…',
                    allowClear: true,
                    dropdownParent: $(document.body),
                    ajax: {
                        url: searchUrl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term || '',
                                ss_id: ssEl ? ssEl.value : null
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.results || []
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 2,
                    language: {
                        inputTooShort: function() {
                            return 'Ingresa al menos 2 caracteres para buscar.';
                        },
                        noResults: function() {
                            return 'No se encontraron usuarios.';
                        },
                        searching: function() {
                            return 'Buscando…';
                        }
                    }
                });

                syncRequesterPickerState();
            }

            initRequesterSelect2();

            if (isAdmin && ssEl) {
                $(ssEl).on('change', function() {
                    initRequesterSelect2();
                    loadAvailableInstruments(ssEl.value, null);
                });
            }

            var instrumentSelect = document.getElementById('indicator_type_id');
            var instrumentsUrl = @json(route('centinela.support.instruments.available'));
            var oldInstrumentId = @json(old('indicator_type_id'));

            function loadAvailableInstruments(ssId, selectedId) {
                if (!instrumentSelect) {
                    return;
                }

                var selected = selectedId !== null && selectedId !== undefined ? selectedId : '';
                instrumentSelect.innerHTML = '<option value="">—</option>';

                if (isAdmin && (!ssId || ssId === '')) {
                    instrumentSelect.disabled = true;
                    return;
                }

                instrumentSelect.disabled = false;

                var url = instrumentsUrl + (isAdmin ? ('?ss_id=' + encodeURIComponent(ssId)) : '');
                fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        (data.results || []).forEach(function(item) {
                            var option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.label;
                            if (String(selected) === String(item.id)) {
                                option.selected = true;
                            }
                            instrumentSelect.appendChild(option);
                        });
                    })
                    .catch(function() {
                        /* mantener solo opción vacía */
                    });
            }

            if (isAdmin && ssEl && ssEl.value) {
                loadAvailableInstruments(ssEl.value, oldInstrumentId);
            }

            var submitBtn = document.getElementById('submit_btn');
            var form = document.getElementById('create_ticket_form');
            if (form && submitBtn) {
                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML =
                        '<i class="voyager-refresh" aria-hidden="true"></i> Creando…';
                });
            }
        });
    </script>
@stop
