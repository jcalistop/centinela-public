<div class="metas-instrument-ui">
    @if (session()->has('config_message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('config_message') }}
        </div>
    @endif

    @if ($errors->has('configGeneral'))
        <div class="alert alert-danger">
            {{ $errors->first('configGeneral') }}
        </div>
    @endif

    {{-- Franja estado del convenio + bitácora --}}
    <div class="cen-dashboard-block" style="margin-bottom: 1rem;">
        <div class="cen-dashboard-block__body clearfix" style="padding-top: 0.85rem; padding-bottom: 0.85rem;">
            <div class="pull-left" style="max-width: 70%;">
                <span class="text-muted" style="margin-right: 0.35rem;">Estado del convenio:</span>
                @if ($isConvenioEnviado ?? false)
                    <span class="label label-success">Enviado</span>
                    <span class="text-muted" style="margin-left: 0.5rem;">
                        @if ($agreement?->isLegacySignature())
                            {{ \App\Models\MetEstablishmentAgreement::LEGACY_SIGNATURE_LABEL }}
                            @if ($agreement->submitted_at)
                                · {{ $agreement->submitted_at->format('d-m-Y H:i') }}
                            @endif
                        @elseif ($agreement?->submittedBy)
                            Firmado por {{ $agreement->submittedBy->name }}
                            @if (!empty($agreement->submittedBy->rut))
                                ({{ $agreement->submittedBy->rut }})
                            @endif
                            ·
                            {{ optional($agreement->submittedBy->role)->display_name ?? optional($agreement->submittedBy->role)->name }}
                            @if ($agreement->submitted_at)
                                · {{ $agreement->submitted_at->format('d-m-Y H:i') }}
                            @endif
                        @else
                            Solo lectura
                        @endif
                    </span>
                    @if (!($canReopenAgreement ?? false))
                        <span class="text-muted" style="display: block; margin-top: 0.35rem;">
                            Para reabrir, la etapa del instrumento debe ser <strong>Formulación</strong>.
                        </span>
                    @endif
                @else
                    <span class="label label-default">Borrador</span>
                    @if ($isCerrado)
                        <span class="text-muted" style="margin-left: 0.5rem;">
                            Instrumento cerrado — no se puede modificar.
                        </span>
                    @endif
                @endif
            </div>
            <div class="pull-right" style="white-space: nowrap;">
                @if ($canReopenAgreement ?? false)
                    <button type="button" class="btn btn-default btn-sm" wire:click="toggleReopenForm"
                        style="margin-right: 0.35rem;">
                        <i class="voyager-refresh" aria-hidden="true"></i>
                        {{ $showReopenForm ?? false ? 'Cancelar reapertura' : 'Reabrir' }}
                    </button>
                @endif
                @if ($configCenterId)
                    <livewire:metas-instrument.establishment-timeline-component :center-id="$configCenterId" :indicator-type-id="$indicatorTypeId"
                        :key="'est-timeline-' . $configCenterId" />
                @endif
            </div>
        </div>

        @if (($canReopenAgreement ?? false) && ($showReopenForm ?? false))
            <div class="cen-dashboard-block__body" style="border-top: 1px solid #eee; padding-top: 0.75rem;">
                <div class="form-group @error('reopenMotivo') has-error @enderror" style="margin-bottom: 0.75rem;">
                    <label for="reopenMotivo">Motivo de reapertura (obligatorio)</label>
                    <textarea id="reopenMotivo" class="form-control" rows="2" wire:model="reopenMotivo"></textarea>
                    @error('reopenMotivo')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>
                <button type="button" class="btn btn-warning btn-sm" wire:click="reopenAgreement"
                    wire:loading.attr="disabled">
                    Confirmar reapertura
                </button>
            </div>
        @endif
    </div>

    @if ($usesInternalUnitMeasurement)
        <x-metas.indicator-config-unit-tabs :unit-statuses="$configUnitStatusesForModal" :active-unit-id="$configMeasurementUnitId" />
    @endif

    @if (!$usesInternalUnitMeasurement || ($configUnitStatusesForModal->isNotEmpty() && $configMeasurementUnitId))
        @if ($configIndicators->isEmpty())
            <div class="alert alert-info">
                No hay indicadores activos en el catálogo para el año activo.
            </div>
        @else
            {{-- Franja resumen + CTAs --}}
            <div class="metas-config-summary"
                wire:key="metas-config-summary-{{ $configSelectedCount }}-{{ $configPonderacionSum }}">
                @if ($activeConfigUnitCodigo)
                    <div class="metas-config-summary__unit text-muted">
                        Unidad <code>{{ $activeConfigUnitCodigo }}</code>
                        @if ($activeConfigUnitNombre)
                            — {{ $activeConfigUnitNombre }}
                        @endif
                    </div>
                @endif

                <div class="metas-config-summary__aside">
                    <div class="metas-config-summary__stats">
                        <span class="metas-config-summary__stat">
                            <span class="metas-config-summary__stat-label">En convenio:</span>
                            <span @class([
                                'label',
                                'label-success' => $configSelectedCountOk,
                                'label-danger' => !$configSelectedCountOk,
                            ])>{{ $configSelectedCount }}</span>
                            @if ($configSelectedCount === 0)
                                <span class="metas-config-summary__stat-hint">sin convenio</span>
                            @else
                                <span class="metas-config-summary__stat-hint">/ mín.
                                    {{ $configMinIndicadores }}</span>
                            @endif
                        </span>
                        <span class="metas-config-summary__stat">
                            <span class="metas-config-summary__stat-label">Suma ponderación:</span>
                            <span @class([
                                'label',
                                'label-success' => $configPonderacionSumOk,
                                'label-danger' => !$configPonderacionSumOk,
                            ])>
                                @if ($configSelectedCount === 0)
                                    —
                                @else
                                    {{ number_format($configPonderacionSum, 2) + 0 }}
                                @endif
                            </span>
                            @if ($configSelectedCount > 0)
                                <span class="metas-config-summary__stat-hint">/ 100</span>
                            @else
                                <span class="metas-config-summary__stat-hint">no aplica</span>
                            @endif
                        </span>
                    </div>

                    <div class="metas-config-actions">
                        @if (
                            !$isCerrado &&
                                !$configIndicators->isEmpty() &&
                                (!$usesInternalUnitMeasurement || ($configUnitStatusesForModal->isNotEmpty() && $configMeasurementUnitId)))
                            <button type="button" class="btn btn-default btn-sm" wire:click="saveDraft"
                                wire:loading.attr="disabled" wire:target="saveDraft">
                                <span wire:loading wire:target="saveDraft"><i class="voyager-loader"></i></span>
                                Guardar borrador
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" wire:click="submitToSs"
                                wire:loading.attr="disabled" wire:target="submitToSs"
                                wire:confirm="¿Enviar el convenio a SS? Quedará como enviado y se podrá consultar solo en modo lectura.">
                                <span wire:loading wire:target="submitToSs"><i class="voyager-loader"></i></span>
                                Enviar a SS
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            @if (!($isConvenioEnviado ?? false) && ($usesInternalUnitMeasurement ?? false) && !empty($incompleteUnitsForSubmit))
                <p class="text-muted text-right" style="margin-top: -0.35rem; margin-bottom: 0.75rem;">
                    Unidades pendientes para enviar:
                    {{ collect($incompleteUnitsForSubmit)->map(fn($u) => $u['nombre'] ?: $u['codigo'])->implode(', ') }}
                </p>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th style="width:50px" class="text-center">
                                <div class="metas-config-col-header">
                                    <span>Aplica</span>
                                    @if (!$isCerrado && ($canEditFullConfig ?? true))
                                        <input type="checkbox" wire:click="toggleAllConfigAplica"
                                            wire:key="hdr-aplica-{{ $configColumnAllAplica ? '1' : '0' }}"
                                            @checked($configColumnAllAplica) title="Marcar o desmarcar toda la columna" />
                                    @endif
                                </div>
                            </th>
                            @unless ($usesInternalUnitMeasurement)
                                <th style="width:70px" class="text-center">
                                    <div class="metas-config-col-header">
                                        <span>Agrupa SS</span>
                                        @if (!$isCerrado && ($canEditFullConfig ?? true))
                                            @if ($configHasAnyAplica)
                                                <input type="checkbox" wire:click="toggleAllConfigAgrupaSs"
                                                    wire:key="hdr-agrupa-on-{{ $configColumnAllAgrupaSs ? '1' : '0' }}"
                                                    @checked($configColumnAllAgrupaSs)
                                                    title="Marcar o desmarcar toda la columna" />
                                            @else
                                                <input type="checkbox" disabled wire:key="hdr-agrupa-off"
                                                    title="Marcar o desmarcar toda la columna" />
                                            @endif
                                        @endif
                                    </div>
                                </th>
                            @endunless
                            <th style="width:60px" class="text-center">
                                <div class="metas-config-col-header">
                                    <span>En convenio</span>
                                    @if (!$isCerrado && ($canEditConvenio ?? false))
                                        @if ($configHasAnyAplica)
                                            <input type="checkbox" wire:click="toggleAllConfigConvenido"
                                                wire:key="hdr-convenido-on-{{ $configColumnAllConvenido ? '1' : '0' }}"
                                                @checked($configColumnAllConvenido)
                                                title="Marcar o desmarcar toda la columna" />
                                        @else
                                            <input type="checkbox" disabled wire:key="hdr-convenido-off"
                                                title="Marcar o desmarcar toda la columna" />
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th style="width:110px; white-space:nowrap">Clave</th>
                            <th>Nombre</th>
                            <th style="width:90px">Periodicidad</th>
                            <th style="width:140px">Meta efectiva</th>
                            <th style="width:130px">Ponderación (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($configIndicators as $indicator)
                            @php
                                $id = $indicator->id;
                                $aplica = (bool) ($configs[$id]['aplica'] ?? true);
                                $agrupaSs = (bool) ($configs[$id]['agrupa_ss'] ?? false);
                                $seleccionado = (bool) ($configs[$id]['seleccionado'] ?? false);
                            @endphp
                            <tr wire:key="cfg-{{ $id }}"
                                class="{{ !$aplica ? 'text-muted' : ($seleccionado ? '' : 'text-muted') }}">
                                <td class="text-center">
                                    <input wire:model.live.debounce.150ms="configs.{{ $id }}.aplica"
                                        type="checkbox" @if ($isCerrado || !($canEditFullConfig ?? true)) disabled @endif />
                                </td>
                                @unless ($usesInternalUnitMeasurement)
                                    <td class="text-center">
                                        <input wire:model.live.debounce.150ms="configs.{{ $id }}.agrupa_ss"
                                            type="checkbox" @if (!$aplica || $isCerrado || !($canEditFullConfig ?? true)) disabled @endif />
                                    </td>
                                @endunless
                                <td class="text-center">
                                    <input wire:model.live.debounce.150ms="configs.{{ $id }}.seleccionado"
                                        type="checkbox" @if (!$aplica || $isCerrado || !($canEditConvenio ?? false)) disabled @endif />
                                </td>
                                <td style="white-space:nowrap"><code>{{ $indicator->clave }}</code></td>
                                <td>
                                    {{ $indicator->nombre }}
                                    @if (($indicatorTypeRoute ?? '') === 'metas-19664')
                                        <x-metas.indicator-priority-tag :priority="$indicator->prioridad" />
                                    @endif
                                </td>
                                <td>{{ $periodicidadPorIndicadorId[$id] ?? 'ANUAL' }}</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        @php
                                            $metaStep =
                                                $indicator->decimales > 0
                                                    ? number_format(
                                                        pow(10, -$indicator->decimales),
                                                        $indicator->decimales,
                                                        '.',
                                                        '',
                                                    )
                                                    : '1';
                                            $metaPlaceholder =
                                                $indicator->meta_valor !== null
                                                    ? number_format(
                                                        (float) $indicator->meta_valor,
                                                        $indicator->decimales,
                                                        '.',
                                                        '',
                                                    )
                                                    : '';
                                        @endphp
                                        <input wire:model="configs.{{ $id }}.meta_efectiva" type="number"
                                            min="0" step="{{ $metaStep }}" class="form-control"
                                            @if (!$aplica || $isCerrado || !($canEditFullConfig ?? true)) disabled @endif
                                            placeholder="{{ $metaPlaceholder }}" />
                                        @if ($indicator->meta_valor !== null)
                                            <span class="input-group-addon"
                                                title="Meta nacional: {{ $metaPlaceholder }}">
                                                <i class="voyager-info-circled"></i>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <input wire:model.live.debounce.200ms="configs.{{ $id }}.ponderacion"
                                        type="number" min="0" max="100" step="0.01"
                                        class="form-control input-sm"
                                        @if (!$seleccionado || $isCerrado) disabled @endif placeholder="0" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Ayuda: columnas y reglas --}}
        <section class="metas-config-help">
            <h4 class="metas-config-help__title">
                <i class="voyager-question" aria-hidden="true"></i>
                Ayuda: columnas y reglas
            </h4>
            <div class="metas-config-help__body">
                @if ($ponderacionesOnlyMode ?? false)
                    <div class="alert alert-info">
                        <i class="voyager-info-circled"></i>
                        Como responsable del establecimiento puede editar las columnas <strong>En convenio</strong> y
                        <strong>Ponderación</strong>.
                    </div>
                    <p class="text-muted">
                        Si marca al menos un <strong>En convenio</strong>, debe incluir un mínimo de
                        <strong>{{ $configMinIndicadores }}</strong>
                        {{ $configMinIndicadores === 1 ? 'indicador' : 'indicadores' }}
                        y las ponderaciones deben sumar exactamente <strong>100</strong>.
                    </p>
                @else
                    <p class="text-muted">
                        No es obligatorio incluir indicadores en el convenio: puede guardar con solo
                        <strong>Aplica</strong>
                        @unless ($usesInternalUnitMeasurement)
                            ,
                            <strong>Agrupa SS</strong>
                        @endunless
                        y <strong>meta efectiva</strong> definidos.
                        Si marca al menos un <strong>En convenio</strong>, debe incluir un mínimo de
                        <strong>{{ $configMinIndicadores }}</strong>
                        {{ $configMinIndicadores === 1 ? 'indicador' : 'indicadores' }}
                        y las ponderaciones deben sumar exactamente <strong>100</strong>.
                    </p>
                @endif
                <x-metas.indicator-config-columns-legend :show-agrupa-ss="!$usesInternalUnitMeasurement" />
            </div>
        </section>
    @endif
</div>
