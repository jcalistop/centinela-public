<div>
    @if (session()->has('config_message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('config_message') }}
        </div>
    @endif

    @if ($errors->has('general'))
        <div class="alert alert-danger">
            {{ $errors->first('general') }}
        </div>
    @endif

    @if ($isCerrado)
        <div class="alert alert-warning">
            <i class="voyager-lock"></i>
            El instrumento está en etapa <strong>CERRADO</strong>. No es posible modificar la configuración.
        </div>
    @endif

    {{-- Selector de establecimiento --}}
    <div class="form-group">
        <label for="center-selector"><strong>Establecimiento</strong></label>
        @if ($includedCenters->isEmpty())
            <div class="alert alert-info" style="margin-top:6px;">
                <i class="voyager-info-circled"></i>
                No hay establecimientos incluidos en la medición. Use el panel de <em>Selección de establecimientos</em>
                para agregar centros antes de configurar indicadores.
            </div>
        @else
            <select id="center-selector" wire:model.live="selectedCenterId" class="form-control"
                style="max-width:420px;">
                <option value="">— Seleccione un establecimiento —</option>
                @foreach ($includedCenters as $center)
                    <option value="{{ $center->id }}">{{ $center->nombre_corto }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if ($usesInternalUnitMeasurement && $selectedCenterId && $includedCenters->isNotEmpty())
        <div class="form-group">
            <label for="measurement-unit-selector"><strong>Unidad de medición</strong></label>
            @if ($measurementUnits->isEmpty())
                <div class="alert alert-warning" style="margin-top:6px;">
                    No hay unidades registradas para este establecimiento. Cree unidades desde la carga del indicador
                    (pantalla de unidades) antes de convenir metas por unidad.
                </div>
            @else
                <select id="measurement-unit-selector" wire:model.live="selectedMeasurementUnitId" class="form-control"
                    style="max-width:420px;">
                    <option value="">— Seleccione una unidad —</option>
                    @foreach ($measurementUnits as $u)
                        <option value="{{ $u->id }}">{{ $u->codigo }}
                            — {{ $u->nombre ?? ($u->unitType?->nombre ?? 'Unidad') }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    @endif

    @if (!$selectedCenterId || $includedCenters->isEmpty())
        {{-- Sin establecimiento seleccionado, no mostrar tabla --}}
    @elseif ($usesInternalUnitMeasurement && ($measurementUnits->isEmpty() || !$selectedMeasurementUnitId))
        <div class="alert alert-info">
            @if ($measurementUnits->isEmpty())
                Cree al menos una unidad de medición para este establecimiento para configurar el convenio por unidad.
            @else
                Seleccione una unidad para ver y editar la ponderación convenida.
            @endif
        </div>
    @else
        <p class="text-muted">
            Configure los indicadores para
            <strong>{{ $includedCenters->firstWhere('id', $selectedCenterId)?->nombre_corto }}</strong>
            (año <strong>{{ session('indicator_year')->ano }}</strong>).
            La suma de ponderaciones de los indicadores seleccionados debe ser exactamente <strong>100</strong>.
            Se requieren al menos <strong>{{ $globalSetting->min_indicadores }} indicadores seleccionados</strong>.
            Ponderación por indicador: entre <strong>{{ $globalSetting->min_ponderacion }}%</strong>
            y <strong>{{ $globalSetting->max_ponderacion }}%</strong>.
        </p>

        @if ($indicators->isEmpty())
            <div class="alert alert-info">
                No hay indicadores en el catálogo para el año activo.
            </div>
        @else
            @php
                $sumaPonderacion = collect($configs)
                    ->filter(fn($c) => (bool) ($c['seleccionado'] ?? false))
                    ->sum(fn($c) => (float) ($c['ponderacion'] ?? 0));
                $cantSeleccionados = collect($configs)->filter(fn($c) => (bool) ($c['seleccionado'] ?? false))->count();
            @endphp

            <div class="row" style="margin-bottom:0.8rem;">
                <div class="col-md-6">
                    <span
                        class="label label-{{ $cantSeleccionados >= $globalSetting->min_indicadores ? 'success' : 'danger' }}">
                        Seleccionados: {{ $cantSeleccionados }} / mínimo {{ $globalSetting->min_indicadores }}
                    </span>
                    &nbsp;
                    <span class="label label-{{ abs($sumaPonderacion - 100) < 0.001 ? 'success' : 'danger' }}">
                        Suma ponderación: {{ number_format($sumaPonderacion, 2) }} / 100
                    </span>
                </div>
                @if (!$isCerrado)
                    <div class="col-md-6 text-right">
                        <button wire:click="save" wire:loading.attr="disabled" class="btn btn-primary">
                            <span wire:loading wire:target="save"><i class="voyager-loader"></i></span>
                            Guardar configuración
                        </button>
                    </div>
                @endif
            </div>

            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center">Sel.</th>
                        <th style="width:110px; white-space:nowrap">Clave</th>
                        <th>Nombre</th>
                        <th style="width:90px">Periodicidad</th>
                        <th style="width:130px">Ponderación (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($indicators as $indicator)
                        @php
                            $id = $indicator->id;
                            $seleccionado = (bool) ($configs[$id]['seleccionado'] ?? false);
                            $ponderacion = $configs[$id]['ponderacion'] ?? '';
                        @endphp
                        <tr wire:key="cfg-{{ $id }}" class="{{ $seleccionado ? '' : 'text-muted' }}">
                            <td class="text-center">
                                <input wire:model.live="configs.{{ $id }}.seleccionado" type="checkbox"
                                    @if ($isCerrado) disabled @endif />
                            </td>
                            <td style="white-space:nowrap"><code>{{ $indicator->clave }}</code></td>
                            <td>{{ $indicator->nombre }}</td>
                            <td>{{ $periodicidadPorIndicadorId[$id] ?? 'ANUAL' }}</td>
                            <td>
                                <input wire:model="configs.{{ $id }}.ponderacion" type="number"
                                    min="{{ $globalSetting->min_ponderacion }}"
                                    max="{{ $globalSetting->max_ponderacion }}" step="0.01"
                                    class="form-control input-sm" @if (!$seleccionado || $isCerrado) disabled @endif
                                    placeholder="0" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (!$isCerrado)
                <div class="text-right">
                    <button wire:click="save" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading wire:target="save"><i class="voyager-loader"></i></span>
                        Guardar configuración
                    </button>
                </div>
            @endif
        @endif
    @endif
</div>
