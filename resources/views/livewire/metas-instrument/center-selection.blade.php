<div>
    @if ($errors->has('general'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ $errors->first('general') }}
        </div>
    @endif

    @if (session()->has('config_message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('config_message') }}
        </div>
    @endif

    @if (session()->has('adosado_info'))
        <div class="alert alert-info alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            <i class="voyager-info-circled"></i> {{ session('adosado_info') }}
        </div>
    @endif

    @if (session()->has('stage_designee_message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            {{ session('stage_designee_message') }}
        </div>
    @endif

    @if (session()->has('stage_designee_error'))
        <div class="alert alert-danger">{{ session('stage_designee_error') }}</div>
    @endif

    @if ($isCerrado)
        <div class="alert alert-warning">
            <i class="voyager-lock"></i>
            El instrumento está en etapa <strong>CERRADO</strong>. No es posible modificar la selección de
            establecimientos.
        </div>
    @endif

    <x-metas.establishments-hub-intro :uses-internal-unit-measurement="$usesInternalUnitMeasurement" :instrument-nombre="$instrumentNombre" :year="session('indicator_year')->ano" />

    @php
        $totalIncluidos = count(array_filter($includedCenterIds));
    @endphp

    <div class="metas-establishments-summary">
        <span class="metas-establishments-summary__stat metas-establishments-summary__stat--included">
            <i class="voyager-hospital"></i>
            <strong>{{ $totalIncluidos }}</strong> de {{ $centers->count() }} establecimientos incluidos
        </span>
    </div>

    @if ($configIssuesByCenterId->isNotEmpty())
        @php
            $affectedCenterNames = $centers
                ->filter(fn($c) => $configIssuesByCenterId->has($c->id))
                ->pluck('nombre_corto')
                ->values();
            $displayNames = $affectedCenterNames->take(5);
            $remainingCount = max(0, $affectedCenterNames->count() - $displayNames->count());
            $bannerMessage =
                $configIssuesByCenterId->count() .
                ' establecimiento(s) con configuración incompleta: ' .
                $displayNames->implode(', ');
            if ($remainingCount > 0) {
                $bannerMessage .= ' y ' . $remainingCount . ' más';
            }
        @endphp
        <div class="metas-section-block metas-establishments-config-banner">
            <x-metas.establishment-config-alert variant="banner" :message="$bannerMessage" />
        </div>
    @endif

    @if ($centers->isEmpty())
        <div class="alert alert-info">
            No se encontraron establecimientos asociados a su Servicio de Salud.
        </div>
    @else
        <table
            class="table table-bordered table-hover table-striped metas-center-selection-table metas-instrument-table">
            <thead>
                <tr>
                    <th class="metas-center-col--incluido">Incluido</th>
                    <th>Establecimiento</th>
                    <th class="metas-center-col--adosados">{{ $usesInternalUnitMeasurement ? 'Unidades' : 'Adosados' }}
                    </th>
                    @if ($canDesignateResponsibles ?? false)
                        <th class="metas-center-col--responsables">Responsables</th>
                    @endif
                    <th class="metas-center-col--accion">Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($centers as $center)
                    @php
                        $isIncluded = isset($includedCenterIds[$center->id]) && $includedCenterIds[$center->id];
                        $isPendingRemove = $pendingRemoveCenterId === $center->id;
                    @endphp
                    <tr class="{{ $isIncluded ? 'metas-center-row--included' : '' }}"
                        wire:key="center-{{ $center->id }}">
                        <td class="text-center">
                            @if ($isIncluded)
                                <span class="metas-center-status--included" title="Incluido en la medición">
                                    <i class="voyager-check"></i>
                                </span>
                            @else
                                <span class="text-muted" title="No incluido"><i class="voyager-x"></i></span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $center->nombre_corto }}</strong> ({{ $center->codigo }})
                            @if ($isIncluded && $configIssuesByCenterId->has($center->id))
                                <div class="metas-center-config-issue">
                                    <a
                                        href="{{ route('metas.instrument.establishments.indicators', [
                                            'indicatorType' => $indicatorTypeRoute,
                                            'center' => $center->id,
                                        ]) }}">
                                        <x-metas.establishment-config-alert :message="$configIssuesByCenterId[$center->id]['message']" />
                                    </a>
                                </div>
                            @endif
                            @if (!$usesInternalUnitMeasurement)
                                @php $adosadoNames = $adosadoNamesByCenterId->get($center->id, collect()); @endphp
                                @foreach ($adosadoNames as $adosadoNombre)
                                    <br><span
                                        class="label label-info metas-center-adosado-label">{{ $adosadoNombre }}</span>
                                @endforeach
                            @endif
                        </td>
                        <td>
                            @if ($usesInternalUnitMeasurement)
                                @if ($isIncluded)
                                    @php $unitCount = $unitCountsByCenterId->get($center->id, 0); @endphp
                                    <button
                                        wire:click="openUnitsPanel({{ $center->id }}, '{{ addslashes($center->nombre_corto) }}')"
                                        class="btn btn-xs btn-default" wire:loading.attr="disabled"
                                        wire:target="openUnitsPanel({{ $center->id }})">
                                        <i class="voyager-list"></i>
                                        Unidades
                                        @if ($unitCount > 0)
                                            <span class="badge">{{ $unitCount }}</span>
                                        @endif
                                    </button>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            @else
                                @if ($isIncluded)
                                    @php
                                        $adosadoCount = $adosadoCountsByCenterId->get($center->id, 0);
                                    @endphp
                                    <button
                                        wire:click="openAdosadoPanel({{ $center->id }}, '{{ addslashes($center->nombre_corto) }}')"
                                        class="btn btn-xs btn-default" wire:loading.attr="disabled"
                                        wire:target="openAdosadoPanel({{ $center->id }})">
                                        <i class="voyager-hospital"></i>
                                        Adosados
                                        @if ($adosadoCount > 0)
                                            <span class="badge">{{ $adosadoCount }}</span>
                                        @endif
                                    </button>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            @endif
                        </td>
                        @if ($canDesignateResponsibles ?? false)
                            <td class="metas-center-col--responsables">
                                @if ($isIncluded)
                                    <div class="metas-center-responsibles-cell">
                                        @php($centerDesignees = $designeesByCenter[$center->id] ?? [])
                                        @if ($centerDesignees !== [])
                                            <div class="metas-center-responsibles-badges">
                                                @foreach ($centerDesignees as $designee)
                                                    <span class="label label-info metas-center-responsible-badge"
                                                        wire:key="designee-{{ $center->id }}-{{ $designee['id'] }}">
                                                        {{ $designee['name'] }}
                                                        <button type="button"
                                                            class="close metas-center-responsible-remove"
                                                            wire:click="removeDesignation({{ $center->id }}, {{ $designee['id'] }})"
                                                            title="Quitar responsable">&times;</button>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                        <button type="button"
                                            class="btn btn-xs btn-default metas-center-responsible-add"
                                            wire:click="openDesignateModal({{ $center->id }})">
                                            Agregar
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endif
                        <td class="text-center metas-center-col--accion">
                            @if ($isIncluded)
                                <a href="{{ route('metas.instrument.establishments.indicators', [
                                    'indicatorType' => $indicatorTypeRoute,
                                    'center' => $center->id,
                                ]) }}"
                                    class="btn btn-xs btn-info" title="Configurar indicadores">
                                    <i class="voyager-check"></i> Indicadores
                                </a>
                            @endif
                            @if (!$isCerrado)
                                @if ($isPendingRemove)
                                    <span class="text-warning metas-center-remove-warning">
                                        <i class="voyager-warning"></i>
                                        Este centro tiene resultados calculados. ¿Confirmar?
                                    </span>
                                    <button wire:click="toggleCenter({{ $center->id }})" wire:loading.attr="disabled"
                                        class="btn btn-xs btn-danger">
                                        Sí, excluir
                                    </button>
                                    <button wire:click="cancelRemove" class="btn btn-xs btn-default">
                                        Cancelar
                                    </button>
                                @elseif ($isIncluded)
                                    <button wire:click="toggleCenter({{ $center->id }})" wire:loading.attr="disabled"
                                        wire:target="toggleCenter({{ $center->id }})" class="btn btn-xs btn-default">
                                        <span wire:loading wire:target="toggleCenter({{ $center->id }})"><i
                                                class="voyager-loader"></i></span>
                                        Excluir
                                    </button>
                                @else
                                    <button wire:click="toggleCenter({{ $center->id }})" wire:loading.attr="disabled"
                                        wire:target="toggleCenter({{ $center->id }})"
                                        class="btn btn-xs btn-success">
                                        <span wire:loading wire:target="toggleCenter({{ $center->id }})"><i
                                                class="voyager-loader"></i></span>
                                        Incluir
                                    </button>
                                @endif
                            @else
                                @if ($isIncluded)
                                    <span class="label label-success">Incluido</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Modal de gestión de establecimientos adosados --}}
    @if ($showAdosadoPanel)
        <div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
            role="dialog">
            <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeAdosadoPanel">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">
                            <i class="voyager-hospital"></i>
                            Adosados — {{ $adosadoPanelCenterName }}
                            <small class="text-muted">año {{ session('indicator_year')->ano }}</small>
                        </h4>
                    </div>
                    <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">

                        @if ($errors->has('adosado'))
                            <div class="alert alert-danger">
                                <i class="voyager-warning"></i> {{ $errors->first('adosado') }}
                            </div>
                        @endif

                        @if ($isCerrado)
                            <div class="alert alert-warning">
                                <i class="voyager-lock"></i>
                                El instrumento está <strong>CERRADO</strong>. No es posible modificar la agrupación.
                            </div>
                        @endif

                        <p class="text-muted" style="margin-bottom:1rem;">
                            Los establecimientos adosados aportan sus datos de numerador y denominador al cálculo de
                            <strong>{{ $adosadoPanelCenterName }}</strong>. Esta configuración aplica únicamente al
                            año <strong>{{ session('indicator_year')->ano }}</strong>.
                        </p>

                        {{-- Lista de adosados actuales --}}
                        <h5>Adosados configurados</h5>
                        @if (empty($currentAdosados))
                            <div class="alert alert-info" style="margin-bottom:1rem;">
                                <i class="voyager-info-circled"></i>
                                No hay establecimientos adosados configurados para este año.
                                Use <strong>Cargar por defecto</strong> o agréguelos manualmente.
                            </div>
                        @else
                            <table class="table table-bordered table-sm" style="margin-bottom:1rem;">
                                <thead>
                                    <tr>
                                        <th>Establecimiento</th>
                                        @if (!$isCerrado)
                                            <th style="width:80px">Acción</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($currentAdosados as $adosado)
                                        <tr wire:key="adosado-row-{{ $adosado['id'] }}">
                                            <td>{{ $adosado['nombre'] }} ({{ $adosado['codigo'] }})</td>
                                            @if (!$isCerrado)
                                                <td class="text-center">
                                                    <button wire:click="removeAdosado({{ $adosado['id'] }})"
                                                        wire:confirm="¿Eliminar este adosado de la agrupación?"
                                                        wire:loading.attr="disabled" class="btn btn-xs btn-danger"
                                                        title="Eliminar adosado">
                                                        <i class="voyager-x"></i>
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        {{-- Agregar adosado manualmente --}}
                        @if (!$isCerrado)
                            <h5>Agregar adosado</h5>
                            <div class="form-inline" style="margin-bottom:0.5rem;">
                                <select wire:model="selectedAdosadoToAdd" class="form-control input-sm"
                                    style="min-width:300px; margin-right:8px;">
                                    <option value="">— Seleccione un establecimiento —</option>
                                    @foreach ($availableCentersForAdosado as $candidate)
                                        <option value="{{ $candidate->id }}">
                                            {{ $candidate->nombre_corto }}
                                            ({{ $candidate->codigo }})
                                        </option>
                                    @endforeach
                                </select>
                                <button wire:click="addAdosado" wire:loading.attr="disabled" wire:target="addAdosado"
                                    class="btn btn-sm btn-primary">
                                    <span wire:loading wire:target="addAdosado">
                                        <i class="voyager-loader"></i>
                                    </span>
                                    Agregar
                                </button>
                            </div>
                            @if ($availableCentersForAdosado->isEmpty())
                                <p class="text-muted" style="font-size:0.9em;">
                                    <i class="voyager-info-circled"></i>
                                    No hay establecimientos disponibles para adosar
                                    (todos están incluidos como centros madre o ya son adosados de otro centro).
                                </p>
                            @endif
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if (!$isCerrado)
                            <button type="button" class="btn btn-default" wire:click="loadDefaultAdosados"
                                wire:loading.attr="disabled" wire:target="loadDefaultAdosados"
                                title="Pre-poblar desde el campo código madre de los establecimientos">
                                <span wire:loading wire:target="loadDefaultAdosados">
                                    <i class="voyager-loader"></i>
                                </span>
                                <i class="voyager-refresh"></i> Cargar por defecto
                            </button>
                        @endif
                        <button type="button" class="btn btn-primary" wire:click="closeAdosadoPanel">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Units panel modal (Ley 20.707) --}}
    @if ($showUnitsPanel)
        <div class="modal fade in metas-instrument-ui admin-dashboard-compass admin-dashboard-compass--modal"
            style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeUnitsPanel">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">
                            <i class="voyager-list"></i>
                            Unidades &mdash; {{ $unitsPanelCenterName }}
                            <small class="text-muted">a&ntilde;o {{ session('indicator_year')->ano }}</small>
                        </h4>
                    </div>
                    <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                        @livewire(
                            'metas-instrument.measurement-units-manager-component',
                            [
                                'centerId' => $unitsPanelCenterId,
                                'indicatorTypeId' => $indicatorTypeId,
                            ],
                            key('units-panel-' . $unitsPanelCenterId)
                        )
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" wire:click="closeUnitsPanel">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showResponsibleModal ?? false)
        <div class="modal fade in metas-instrument-ui admin-dashboard-compass admin-dashboard-compass--modal"
            style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close"
                            wire:click="closeResponsibleModal"><span>&times;</span></button>
                        <h4 class="modal-title">Responsable — {{ $responsibleCenterName }}</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group" wire:ignore>
                            <label>Usuario referente</label>
                            <select id="responsible-user-select" class="form-control" style="width:100%">
                                <option value="">— Seleccione —</option>
                                @foreach ($referenteUsers as $user)
                                    <option value="{{ $user->id }}" @selected($selectedResponsibleUserId === $user->id)>
                                        {{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default"
                            wire:click="closeResponsibleModal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn-save-responsible">
                            <i class="voyager-check"></i> Agregar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@script
    <script>
        $wire.on('open-responsible-select2', () => {
            setTimeout(() => {
                const $sel = $('#responsible-user-select');
                if ($sel.length && $sel.hasClass('select2-hidden-accessible')) {
                    $sel.select2('destroy');
                }
                if ($sel.length) {
                    $sel.select2({
                        placeholder: 'Buscar usuario...',
                        allowClear: true,
                        width: '100%',
                    });
                }
            }, 150);
        });

        $(document).on('click', '#btn-save-responsible', function() {
            const userId = parseInt($('#responsible-user-select').val(), 10);
            if (!userId) {
                return;
            }
            @this.saveDesignation(userId);
        });
    </script>
@endscript
