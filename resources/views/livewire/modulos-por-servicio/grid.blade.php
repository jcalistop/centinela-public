<div>
    <style>
        .switch-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            vertical-align: middle;
        }

        .switch-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }

        .switch-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: #fff;
            transition: .3s;
            border-radius: 50%;
        }

        .switch-toggle input:checked+.switch-slider {
            background-color: #22A7F0;
        }

        .switch-toggle input:checked+.switch-slider:before {
            transform: translateX(20px);
        }

        .grid-table th {
            white-space: nowrap;
            text-align: center;
            vertical-align: middle !important;
            font-size: 12px;
        }

        .grid-table td {
            vertical-align: middle !important;
        }

        .grid-table td.ss-name {
            white-space: nowrap;
            min-width: 220px;
        }

        .grid-table td.toggle-cell {
            text-align: center;
            min-width: 60px;
        }

        .grid-table .ss-name i {
            margin-right: 6px;
            color: #62a8ea;
        }

        .switch-toggle.is-disabled {
            opacity: 0.45;
            pointer-events: none;
        }

        .switch-toggle.is-disabled .switch-slider {
            cursor: not-allowed;
        }

        .catalog-disabled-note {
            margin-bottom: 12px;
        }
    </style>

    <div class="page-content browse container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body" style="overflow-x: auto;">
                        @if (!empty($catalogDisabledModules))
                            <div class="alert alert-warning catalog-disabled-note" role="status">
                                Instrumentos deshabilitados en el catálogo (no editables aquí hasta reactivarlos en
                                tipos de indicador):
                                <strong>
                                    {{ collect(array_keys($catalogDisabledModules))->map(fn($key) => \App\Models\ServiceModuleToggle::moduleLabel($key))->implode(', ') }}
                                </strong>
                            </div>
                        @endif
                        @if ($ssList->isEmpty())
                            <p class="text-muted">No hay Servicios de Salud registrados.</p>
                        @else
                            <table class="table table-hover grid-table">
                                <thead>
                                    <tr>
                                        <th class="text-left" style="min-width:220px">Servicio de Salud</th>
                                        @foreach ($modules as $moduleKey)
                                            <th>
                                                {{ \App\Models\ServiceModuleToggle::moduleLabel($moduleKey) }}
                                                @if ($this->isCatalogDisabled($moduleKey))
                                                    <br><small class="text-muted">Catálogo Off</small>
                                                @endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ssList as $ss)
                                        <tr>
                                            <td class="ss-name">
                                                <i class="voyager-company"></i>
                                                {{ $ss->nombre }}
                                            </td>
                                            @foreach ($modules as $moduleKey)
                                                @php
                                                    $enabled = $this->toggles[$ss->id][$moduleKey] ?? true;
                                                    $catalogOff = $this->isCatalogDisabled($moduleKey);
                                                @endphp
                                                <td class="toggle-cell">
                                                    <label class="switch-toggle {{ $catalogOff ? 'is-disabled' : '' }}"
                                                        @if ($catalogOff) title="Instrumento deshabilitado en el catálogo" @endif>
                                                        <input type="checkbox" @checked($enabled)
                                                            @disabled($catalogOff)
                                                            @if (!$catalogOff) wire:click="toggle({{ $ss->id }}, '{{ $moduleKey }}')" @endif>
                                                        <span class="switch-slider"></span>
                                                    </label>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
