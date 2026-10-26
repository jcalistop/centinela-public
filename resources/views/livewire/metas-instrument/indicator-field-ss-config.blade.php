<div>
    @if (session()->has('field_ss_config_message'))
        <div class="alert alert-success alert-dismissible metas-field-ss-alert" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('field_ss_config_message') }}
        </div>
    @endif

    @if (session()->has('field_ss_config_warning'))
        <div class="alert alert-warning alert-dismissible metas-field-ss-alert" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            <strong>Atención:</strong> {{ session('field_ss_config_warning') }}
        </div>
    @endif

    <x-metas.bordered-panel variant="warning" id="field-ss-config">
        <x-slot:heading>
            <i class="voyager-settings"></i> Tipos de entrada y agregación por Servicio de Salud
        </x-slot:heading>

        <p class="text-muted metas-field-ss-intro">
            Defina el tipo de entrada y el tipo de agregación de cada variable antes de que los referentes carguen
            datos para este indicador.
        </p>

        @if ($fields->isEmpty())
            <p class="text-muted metas-field-ss-empty">Este indicador no tiene variables definidas en la
                maqueta.</p>
        @else
            <div class="table-responsive">
                <table
                    class="table table-bordered table-hover table-striped metas-instrument-table metas-field-ss-table">
                    <thead>
                        <tr>
                            <th class="metas-field-ss-col--variable">Variable</th>
                            <th>Etiqueta</th>
                            <th class="metas-field-ss-col--tipo">Tipo de entrada</th>
                            <th class="metas-field-ss-col--tipo">Tipo de agregación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fields as $field)
                            <tr wire:key="field-ss-config-{{ $field->id }}">
                                <td><code>{{ $field->variable_key }}</code></td>
                                <td>{{ $field->label }}</td>
                                <td>
                                    <select class="form-control input-sm"
                                        wire:model.live="fieldConfigs.{{ $field->id }}.input_type"
                                        @if ($fieldHasCapturedData[$field->id] ?? false) disabled @endif>
                                        @foreach ($dataFieldInputTypeOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @if ($fieldHasCapturedData[$field->id] ?? false)
                                        <p class="text-muted metas-field-ss-hint">
                                            El tipo de entrada queda fijo porque ya hay datos cargados para esta
                                            variable en su Servicio de Salud y el año vigente.
                                        </p>
                                    @endif
                                    @error("fieldConfigs.{$field->id}.input_type")
                                        <span class="help-block text-danger metas-field-ss-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </td>
                                <td>
                                    @if (
                                        \App\Enums\Metas\DataFieldInputType::tryFrom(
                                            $fieldConfigs[$field->id]['input_type'] ?? '')?->requiresAggregationType())
                                        <select class="form-control input-sm"
                                            wire:model="fieldConfigs.{{ $field->id }}.aggregation_type">
                                            @foreach ($dataFieldAggregationTypeOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($fieldHasCapturedData[$field->id] ?? false)
                                            <p class="text-warning metas-field-ss-hint--warning">
                                                <strong>Atención:</strong> cambiar el tipo de agregación altera
                                                cómo se interpretan los datos ya ingresados al calcular resultados
                                                y resúmenes.
                                            </p>
                                        @endif
                                        @error("fieldConfigs.{$field->id}.aggregation_type")
                                            <span class="help-block text-danger metas-field-ss-error">
                                                {{ $message }}
                                            </span>
                                        @enderror
                                    @else
                                        <span class="text-muted">No aplica</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (
            $fields->isNotEmpty() &&
                $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_COMPLETE &&
                $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_NO_FIELDS)
            <div class="metas-instrument-field-ss-config-alerta metas-field-ss-config-alerta">
                <div class="componente-corte-alerta"
                    title="Falta definir tipos de entrada y agregación para este indicador en su Servicio de Salud.">
                    <span class="glyphicon glyphicon-exclamation-sign text-warning" aria-hidden="true"></span>
                    <strong>Carga de datos</strong>
                    <div class="small text-muted">
                        @if ($fieldSsConfigStatus === \App\Services\Metas\MetFieldSsConfigService::STATUS_PARTIAL)
                            Tipos de entrada pendientes para su Servicio de Salud
                        @else
                            Sin configuración de tipos de entrada/agregación por SS para este indicador
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($fields->isNotEmpty())
            <x-slot:footer>
                <button type="button" class="btn btn-warning" wire:click="saveConfiguration"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveConfiguration">
                        <i class="voyager-check"></i> Guardar configuración
                    </span>
                    <span wire:loading wire:target="saveConfiguration">Guardando…</span>
                </button>
            </x-slot:footer>
        @endif
    </x-metas.bordered-panel>
</div>
