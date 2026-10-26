<div>
    <x-panel-component col="12" panel="action">
        <x-slot name="title">
            {{ $action->numero }}
            @can('edit', app('App\Models\ComAction'))
                <a href="{{ route('voyager.acciones.edit', $action->id) }}" class="btn btn-default" style="color:#016CBA;">
                    <i class="glyphicon glyphicon-pencil"></i> Editar {{ $action->numero }}
                </a>
            @endcan
            @if ($action->comSensitivityTables->count() == 0)
                @can('delete', app('App\Models\ComAction'))
                    <x-botones.delete-button-component table="com_actions" :id="$action->id" />
                @endcan
            @endif
        </x-slot>
        <x-slot name="body">
            <table class="table table-bordered table-action" width="100%">
                @php
                    $cutoffClave = $action->comIndicatorCutoff->cutoff->clave;
                    $cutoffNombre = $action->comIndicatorCutoff->cutoff->nombre_corte;
                    $isIndicadorOrRequisito =
                        $cutoffClave === \App\Enums\Corte\CutoffClave::Indicador ||
                        $cutoffClave === \App\Enums\Corte\CutoffClave::Requisito;
                @endphp
                @if (!is_null($action->observaciones) && $action->observaciones != 0 && $action->observaciones != '')
                    <tr>
                        <th width="30%">Observaciones OOOT</th>
                        <td width="70%">{!! $action->observaciones !!}</td>
                    </tr>
                @endif
                @if ($showVerifiableEvaluation ?? false)
                    <tr>
                        <th>Carga de medios de verificación para {{ $cutoffNombre }}
                            @php $etapa_vigente = 'AUTOEVALUACION'; @endphp
                            @can('current-add-edit', [$action->id, $etapa_vigente])
                                <a href="{{ route('voyager.medios-verificacion.create', ['com_action_id' => $action->id, 'verifiable_type' => 'EVALUACION']) }}"
                                    class="btn btn-sm btn-success pull-right"><i class="voyager-list-add"></i> Agregar
                                </a>
                            @endcan
                        </th>
                        <td style="padding:0;">
                            @php $scopeWhere = ['com_action_id' => $action->id, 'verifiable_type' => 'EVALUACION'] @endphp
                            <x-browse-view-component table="com_verifiable_documents" :scopeWhere="$scopeWhere"
                                showButtons="{{ Gate::allows('current-add-edit', [$action->id, $etapa_vigente]) }}"
                                download="{{ Gate::allows('current-download', $action->comIndicatorCutoff->com_indicator_id) }}" />
                        </td>
                    </tr>
                @endif
                @unless ($hideSensitivityAndEvaluationsRows ?? false)
                    <tr>
                        <th>Tabla de sensibilidad
                            @if ($isIndicadorOrRequisito)
                                para {{ $cutoffClave->value }}
                            @endif
                            @can('add', app('App\Models\ComSensitivityTable'))
                                <a href="{{ route('voyager.tablas-sensibilidad-comges.create', ['com_action_id' => $action->id]) }}"
                                    class="btn btn-sm btn-success pull-right"><i class="voyager-list-add"></i> Agregar
                                </a>
                            @endcan
                        </th>
                        <td style="text-center">
                            <div class="container-fluid">
                                @can('edit', app('App\Models\ComSensitivityDependency'))
                                    <div class="row">
                                        <div class="col-sm-8"></div>
                                        <div class="col-sm-4 text-center text-bold">Seleccione tabla de sensibilidad</div>
                                    </div>
                                @endcan
                                @php
                                    $i = 0;
                                @endphp
                                @foreach ($action->comSensitivityTables as $comSensitivityTable)
                                    @can('read', app('App\Models\ComSensitivityTable'))
                                        @if ($comSensitivityTable->comSensitivityDependencies->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)->count() == 0)
                                            @continue
                                        @endif
                                    @endcan
                                    @php $i++; @endphp
                                    <div class="row">
                                        <div class="col-sm-8 text-center">
                                            <x-acciones.sensitivity-table-component :id="$comSensitivityTable->id" />
                                        </div>
                                        <div class="col-sm-4 text-center">
                                            <div class="btn-group btn-group-vertical" role="group">
                                                @can('edit', app('App\Models\ComSensitivityDependency'))
                                                    @if ($comSensitivityTable->comSensitivityDependencies->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)->count() == 1)
                                                        <a href="#" class="btn btn-sm btn-success" disabled>
                                                            <i class="voyager-check"></i> Seleccionado
                                                        </a>
                                                    @else
                                                        <a href="{{ route('habilitar-meta-comges', $comSensitivityTable->id) }}"
                                                            class="btn btn-sm btn-primary"
                                                            onclick="return mostrarConfirmacion('Va a asignar esta tabla de sensibilidad para su Servicio de Salud \n ¿Está seguro?')">
                                                            <i class="glyphicon glyphicon-hand-up"></i> Seleccionar
                                                        </a>
                                                    @endif
                                                @endcan

                                                @php
                                                    $actionDataType = collect();
                                                    $actionDataType->slug = 'tablas-sensibilidad-comges';
                                                    $actionDataType->model_name = 'App\Models\ComSensitivityTable';
                                                @endphp

                                                <x-botones.edit-button-component :dataType="$actionDataType" :id="$comSensitivityTable->id" />
                                                <x-botones.delete-button-component table="com_sensitivity_tables"
                                                    :id="$comSensitivityTable->id" />
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                @if ($action->comSensitivityTables->count() == 0)
                                    <div class="alert alert-danger">
                                        <p class="text-center text-bold">No hay tablas de sensibilidad creadas para esta
                                            acción.</p>
                                        @can('add', app('App\Models\ComSensitivityTable'))
                                            <p class="text-center">Por favor, cree una tabla de sensibilidad utilizando el
                                                botón
                                                "Agregar" en la sección de arriba.</p>
                                        @endcan
                                    </div>
                                @elseif ($i == 0)
                                    <div class="alert alert-warning">
                                        <p class="text-center text-bold text-danger">Control de Gestión debe seleccionar una
                                            tabla de sensibilidad para su Servicio de Salud</p>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endunless
                @if ($showVerifiableAppeal ?? false)
                    <tr>
                        <th>Carga de Apelaciones
                            @php $etapa_vigente = 'APELACION'; @endphp
                            @can('current-add-edit', [$action->id, $etapa_vigente])
                                <a href="{{ route('voyager.medios-verificacion.create', ['com_action_id' => $action->id, 'verifiable_type' => 'APELACION']) }}"
                                    class="btn btn-sm btn-success pull-right"><i class="voyager-list-add"></i> Agregar
                                </a>
                            @endcan
                        </th>
                        <td style="padding:0;">
                            @php $scopeWhere = ['com_action_id' => $action->id, 'verifiable_type' => 'APELACION'] @endphp
                            <x-browse-view-component table="com_verifiable_documents" :scopeWhere="$scopeWhere"
                                showButtons="{{ Gate::allows('current-add-edit', [$action->id, $etapa_vigente]) }}" />
                        </td>
                    </tr>
                @endif
                @unless ($hideSensitivityAndEvaluationsRows ?? false)
                    <tr>
                        <td colspan="2" style="padding:0;">
                            <x-acciones.comges-evaluations-component :action="$action" />
                        </td>
                    </tr>
                @endunless
            </table>
        </x-slot>
    </x-panel-component>
    @push('javascript')
        <script>
            function mostrarConfirmacion(mensaje) {
                const confirmacion = confirm(mensaje);
                if (confirmacion) {
                    return true;
                } else {
                    return false;
                }
            }
        </script>
    @endpush

</div>
