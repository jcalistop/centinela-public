@php
    use Carbon\Carbon;
@endphp
@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
    @include('partials.indicators.comges-indicator-read-styles')
    <style>
        .indicador-ficha-nombre {
            font-size: 1.2em;
            font-weight: 600;
            color: #444;
            margin: 0 0 0.5rem 0;
        }

        .indicador-ponderacion-badge {
            background: #fff8e7;
            border: 2px solid #e6a817;
            border-radius: 8px;
            padding: 10px 16px;
            display: inline-block;
            min-width: 100%;
        }

        .indicador-ponderacion-badge .ponderacion-valor {
            font-size: 1.9em;
            font-weight: 700;
            color: #c57c00;
            display: block;
            line-height: 1.1;
        }

        .indicador-ponderacion-badge .ponderacion-etiqueta {
            font-size: 0.7em;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .indicador-campo {
            padding: 7px 10px;
            border-bottom: 1px solid #efefef;
            background: #fafafa;
            margin-bottom: 4px;
            border-radius: 3px;
        }

        .indicador-campo .campo-label {
            font-weight: 600;
            color: #777;
            font-size: 0.78em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
            margin-bottom: 1px;
        }

        .indicador-campo .campo-valor {
            color: #333;
            font-size: 0.95em;
        }

        .indicador-campo-largo {
            border-left: 3px solid #e6a817;
            padding: 6px 0 6px 12px;
            margin-bottom: 10px;
        }

        .indicador-campo-largo .campo-label {
            font-weight: 600;
            color: #777;
            font-size: 0.78em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
            margin-bottom: 3px;
        }

        .indicador-campo-largo .campo-valor {
            color: #333;
        }

        .indicador-fuentes {
            display: flex;
            gap: 12px;
            margin: 8px 0 6px 18px;
        }

        .indicador-fuente-card {
            flex: 1;
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .indicador-fuente-card .fuente-icono {
            font-size: 1.3em;
            color: #bbb;
            flex-shrink: 0;
        }

        .indicador-fuente-card .fuente-cuerpo .fuente-label {
            font-size: 0.72em;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: block;
        }

        .indicador-fuente-card .fuente-cuerpo .fuente-valor {
            font-weight: 600;
            color: #444;
            font-size: 0.9em;
        }

        .indicador-estado-card {
            text-align: center;
            padding: 14px 10px;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            background: #fafafa;
            margin-bottom: 10px;
        }

        .indicador-estado-card .estado-icono {
            font-size: 1.5em;
            display: block;
            margin-bottom: 6px;
        }

        .indicador-estado-card .estado-label {
            font-size: 0.7em;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }

        .indicador-estado-card .estado-valor {
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
        }
    </style>
@stop

@section('page_title', __('voyager::generic.view') . ' ' . $dataType->getTranslatedAttribute('display_name_singular'))

@section('content')
    <div class="page-content compass container-fluid">
        @include('voyager::alerts')
        @include('partials.indicators.comges-back-to-commitments')
        <x-indicators.detail-tabs :indicator-module="'comges'" :indicator-id="$dataTypeContent->id">
            <x-panel-component col='12' panel="warning">
                <x-slot name="title">
                    <span class="text-uppercase text-bold">{{ $dataTypeContent->clave }}
                        ({{ $dataTypeContent->comCommitment->year->ano }})<span></x-slot>
                <x-slot name="body">
                    @php
                        $comIndicatorSetting = $dataTypeContent->comIndicatorSettings
                            ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
                            ->first();
                        $users = $comIndicatorSetting ? $comIndicatorSetting->users->unique() : collect();
                    @endphp

                    {{-- ENCABEZADO: Nombre + Ponderación --}}
                    <div class="row" style="margin-bottom: 1rem;">
                        <div class="col-md-9 col-sm-8">
                            <p class="indicador-ficha-nombre">{{ $dataTypeContent->nombre }}</p>
                            <div style="margin-top: 6px;">
                                <i class="glyphicon glyphicon-user" style="color: #bbb; margin-right: 4px;"></i>
                                <span
                                    style="font-size: 0.82em; color: #999; text-transform: uppercase; letter-spacing: 0.3px;">Referentes:</span>
                                @forelse ($users as $user)
                                    <a href="{{ route('voyager.users.show', $user->id) }}"
                                        class="label label-default metas-referente-label-link"
                                        style="font-weight:normal; margin-right:2px; display:inline-block;"
                                        title="Ver perfil y correo electrónico">
                                        <i class="voyager-person"></i> {{ $user->name }}
                                    </a>
                                @empty
                                    <span class="text-muted" style="font-size: 0.85em; margin-left: 5px;">Sin referentes
                                        asignados</span>
                                @endforelse
                            </div>
                            <div style="margin-top: 6px;">
                                <i class="glyphicon glyphicon-tags" style="color: #bbb; margin-right: 4px;"></i>
                                <span
                                    style="font-size: 0.82em; color: #999; text-transform: uppercase; letter-spacing: 0.3px;">Ámbitos:</span>
                                @php
                                    $comAmbitos = $dataTypeContent->indicatorAmbitos ?? collect();
                                @endphp
                                @include('partials.indicators.ambitos-badges', [
                                    'ambitos' => $comAmbitos,
                                    'linkToDetail' => true,
                                ])
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-4 text-center">
                            <div class="indicador-ponderacion-badge">
                                <span class="ponderacion-etiqueta">Ponderación al corte</span>
                                <span class="ponderacion-valor">{{ $dataTypeContent->ponderacion_indicador }}%</span>
                            </div>
                        </div>
                    </div>

                    <hr style="margin: 0.3rem 0 1.2rem;">

                    {{-- CAMPOS DESCRIPTIVOS --}}
                    @php
                        $descripcionItems = is_string($dataTypeContent->descripcion)
                            ? json_decode($dataTypeContent->descripcion, true)
                            : null;
                        $descripcionItems = is_array($descripcionItems) ? $descripcionItems : [];
                    @endphp
                    @if ($descripcionItems !== [])
                        @php
                            $ordenLargos = [
                                'Objetivo del Indicador',
                                'Exclusiones',
                                'Fórmula de Cálculo',
                                'Formula de Calculo',
                            ];
                            $camposLargos = $ordenLargos;
                            $clavesFuentes = ['Fuente Numerador', 'Fuente Denominador'];
                            $clavesExcluidas = array_merge($camposLargos, $clavesFuentes);
                            $camposCortos = array_filter(
                                $descripcionItems,
                                fn($k) => !in_array($k, $clavesExcluidas),
                                ARRAY_FILTER_USE_KEY,
                            );
                            // Reordenar según $ordenLargos
                            $camposLargosFiltrados = [];
                            foreach ($ordenLargos as $ord) {
                                if (array_key_exists($ord, $descripcionItems)) {
                                    $camposLargosFiltrados[$ord] = $descripcionItems[$ord];
                                }
                            }
                            $camposFuentes = array_filter(
                                $descripcionItems,
                                fn($k) => in_array($k, $clavesFuentes),
                                ARRAY_FILTER_USE_KEY,
                            );
                            $clavesFormula = ['Fórmula de Cálculo', 'Formula de Calculo'];
                        @endphp

                        <div class="row">
                            @foreach ($camposCortos as $clave => $valor)
                                <div class="col-md-6 col-sm-6">
                                    <div class="indicador-campo">
                                        <span class="campo-label">{{ $clave }}</span>
                                        <span class="campo-valor">{{ $valor }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @foreach ($camposLargosFiltrados as $clave => $valor)
                            <div class="indicador-campo-largo" style="margin-top: 0.8rem;">
                                <span class="campo-label">{{ $clave }}</span>
                                <span class="campo-valor">{{ $valor }}</span>
                            </div>
                            @if (in_array($clave, $clavesFormula) && count($camposFuentes) > 0)
                                <div class="indicador-fuentes">
                                    @foreach ($camposFuentes as $fClave => $fValor)
                                        <div class="indicador-fuente-card">
                                            <i class="glyphicon glyphicon-list-alt fuente-icono"></i>
                                            <div class="fuente-cuerpo">
                                                <span class="fuente-label">{{ $fClave }}</span>
                                                <span class="fuente-valor">{{ $fValor }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    @endif
                    @php
                        $indicatorTypeSetting = $dataTypeContent->comCommitment->indicatorType->indicatorTypeSettings
                            ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
                            ->where('year_id', Helper::showCurrentYear()->id)
                            ->first();
                        if ($indicatorTypeSetting->etapa_vigente == 'CERRADO') {
                            $open = 'Cerrado';
                            $close = 'Cerrado';
                        } elseif (!is_null($comIndicatorSetting) && $comIndicatorSetting->mantener_abierto) {
                            $open = 'Abierto, sin restricción de fecha';
                            $close = 'Abierto, sin restricción de fecha';
                        } elseif (
                            !is_null(optional($comIndicatorSetting)->fecha_apertura) &&
                            !is_null(optional($comIndicatorSetting)->fecha_cierre)
                        ) {
                            $open =
                                Carbon::createFromFormat('Y-m-d', $comIndicatorSetting->fecha_apertura)->format(
                                    'd-m-Y',
                                ) . ' 08:00 horas';
                            $close =
                                Carbon::createFromFormat('Y-m-d', $comIndicatorSetting->fecha_cierre)->format('d-m-Y') .
                                ' 17:00 horas';
                        } else {
                            $open = 'Cerrado';
                            $close = 'Cerrado';
                        }
                    @endphp

                    <div class="row" style="margin-top: 1.5rem; margin-bottom: 0.5rem;">
                        <div class="col-md-4">
                            <div class="indicador-estado-card">
                                <i class="glyphicon glyphicon-flag estado-icono" style="color: #e6a817;"></i>
                                <span class="estado-label">Etapa vigente</span>
                                <span class="estado-valor">{{ $indicatorTypeSetting->etapa_vigente }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="indicador-estado-card">
                                <i class="glyphicon glyphicon-time estado-icono" style="color: #5cb85c;"></i>
                                <span class="estado-label">Apertura</span>
                                <span class="estado-valor">{{ $open }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="indicador-estado-card">
                                <i class="glyphicon glyphicon-time estado-icono" style="color: #d9534f;"></i>
                                <span class="estado-label">Cierre</span>
                                <span class="estado-valor">{{ $close }}</span>
                            </div>
                        </div>
                    </div>

                    <x-indicadores.indicator-results-component :dataTypeContent="$dataTypeContent" />
                </x-slot>
                <x-slot name="footer">
                    @can('edit', app('App\Models\ComIndicatorSetting'))
                        <a href="{{ route('configurar-indicador-comges.upsert', $dataTypeContent->id) }}"
                            class="btn btn-warning">
                            <i class="voyager-settings"></i>&nbsp;Configurar Indicador
                        </a>
                    @endcan
                    @can('edit', $dataTypeContent)
                        <a href="{{ route('voyager.' . $dataType->slug . '.edit', $dataTypeContent->getKey()) }}"
                            class="btn btn-info">
                            <i class="glyphicon glyphicon-pencil"></i>&nbsp;{{ __('voyager::generic.edit') }}
                        </a>
                    @endcan
                    @can('delete', $dataTypeContent)
                        @if ($isSoftDeleted)
                            <a href="{{ route('voyager.' . $dataType->slug . '.restore', $dataTypeContent->getKey()) }}"
                                title="{{ __('voyager::generic.restore') }}" class="btn btn-default restore"
                                data-id="{{ $dataTypeContent->getKey() }}" id="restore-{{ $dataTypeContent->getKey() }}">
                                <i class="voyager-trash"></i>&nbsp;{{ __('voyager::generic.restore') }}
                            </a>
                        @else
                            <a href="javascript:;" title="{{ __('voyager::generic.delete') }}" class="btn btn-danger delete"
                                data-id="{{ $dataTypeContent->getKey() }}" id="delete-{{ $dataTypeContent->getKey() }}">
                                <i class="voyager-trash"></i>&nbsp;{{ __('voyager::generic.delete') }}
                            </a>
                        @endif
                    @endcan
                </x-slot>
            </x-panel-component>
            @can('add', app('App\Models\ComIndicatorCutoff'))
                <a href="{{ route('voyager.cortes-comges.create', ['com_indicator_id' => $dataTypeContent->getKey()]) }}"
                    class="btn btn-success">
                    <i class="voyager-plus"></i> Crear pestaña
                </a>
            @endcan
            <ul class="nav nav-tabs">
                @php
                    $currentCut =
                        $dataTypeContent->comCommitment->indicatorType->indicatorTypeSettings
                            ->where('year_id', Helper::showCurrentYear()->id)
                            ->first()->cutoff_id ?? null;
                    if (is_null($currentCut)) {
                        $currentCut = $dataTypeContent->comIndicatorCutoffs->first()->cutoff_id ?? null;
                    }
                    $stateTabs = json_decode(optional($comIndicatorSetting)->pestanas_editables ?? '[]', true);
                    if (!is_array($stateTabs)) {
                        $stateTabs = [];
                    }
                @endphp

                @foreach ($dataTypeContent->comIndicatorCutoffs as $comIndicatorCutoff)
                    <li class="@if ($loop->first) active @endif">
                        <a data-toggle="tab" href="#{{ $comIndicatorCutoff->cutoff->id }}">
                            {!! in_array($comIndicatorCutoff->id, $stateTabs)
                                ? '<i class="voyager-calendar"></i>'
                                : '<i class="voyager-lock"></i>' !!}
                            <br>{{ $comIndicatorCutoff->cutoff->nombre_corte }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                @foreach ($dataTypeContent->comIndicatorCutoffs as $comIndicatorCutoff)
                    <div role="tabpanel" class="tab-pane @if ($loop->first) active @endif"
                        id="{{ $comIndicatorCutoff->cutoff->id }}">
                        @can('edit', app('App\Models\ComIndicatorCutoff'))
                            <a href="{{ route('voyager.cortes-comges.edit', $comIndicatorCutoff->id) }}" class="btn btn-info">
                                <i class="glyphicon glyphicon-pencil"></i> Editar
                                {{ $comIndicatorCutoff->cutoff->nombre_corte }}
                            </a>
                        @endcan
                        @if ($comIndicatorCutoff->comActions->count() == 0)
                            @can('delete', app('App\Models\ComIndicatorCutoff'))
                                <x-botones.delete-button-component table="com_indicator_cutoffs" :id="$comIndicatorCutoff->id"
                                    etiqueta="Eliminar {{ $comIndicatorCutoff->cutoff->nombre_corte }}" />
                            @endcan
                        @endif
                        @can('add', app('App\Models\ComAction'))
                            <a href="{{ route('voyager.acciones.create', ['com_indicator_cutoff_id' => $comIndicatorCutoff->id]) }}"
                                class="btn btn-success">
                                <i class="voyager-plus"></i> Crear Sección {{ $comIndicatorCutoff->cutoff->nombre_corte }}
                            </a>
                        @endcan
                        @can('add', app('App\Models\ComIndicatorCutoff'))
                            <button type="button" class="btn btn-warning" data-toggle="modal"
                                data-target="#modal-duplicar-corte" data-cutoff-id="{{ $comIndicatorCutoff->id }}"
                                data-cutoff-nombre="{{ $comIndicatorCutoff->cutoff->nombre_corte }}">
                                <i class="glyphicon glyphicon-copy"></i> Duplicar
                                {{ $comIndicatorCutoff->cutoff->nombre_corte }}
                            </button>
                        @endcan

                        @php
                            $actionsPonderacion = $comIndicatorCutoff->comActions
                                ->map(fn($action) => (float) ($action->ponderacion_accion ?? 0))
                                ->sum();
                            $actionsPonderacionRounded = round($actionsPonderacion, 2);
                            $showPonderacionAlert = in_array(
                                Auth::user()->role->name,
                                ['parametrizador', 'admin'],
                                true,
                            );
                        @endphp

                        @if ($showPonderacionAlert && $actionsPonderacionRounded != 100.0)
                            <div class="alert alert-warning" style="margin-top: 10px;">
                                <strong>Atención:</strong> La suma de ponderaciones de las acciones para
                                <strong>{{ $comIndicatorCutoff->cutoff->nombre_corte }}</strong>
                                ({{ $actionsPonderacionRounded }}%)
                                debe ser 100%.
                            </div>
                        @endif
                        {{-- INICIA VISUALIZACION --}}
                        @php
                            $comVisualization = $comIndicatorCutoff->comVisualizations
                                ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
                                ->first();

                            $renderData =
                                $comVisualization &&
                                !is_null($comVisualization->data) &&
                                $comVisualization->data != '' &&
                                $comVisualization->data != '{}';

                            if ($renderData) {
                                $array = json_decode($comVisualization->data, true);
                                $firstRow = array_keys(reset($array));
                                $rows = [];
                                foreach ($array as $establecimiento => $meses) {
                                    $rows[] = array_merge([$establecimiento], array_values($meses));
                                }
                            }
                        @endphp

                        @if (is_null($comVisualization))
                            @can('add', app('App\Models\ComVisualization'))
                                <a href="{{ route('voyager.visualizaciones.create', ['comIndicatorCutoffId' => $comIndicatorCutoff->id]) }}"
                                    class="btn btn-success pull-right">
                                    <i class="voyager-plus"></i> Agregar Visualización
                                </a>
                            @endcan
                        @elseif($comVisualization->habilitado)
                            <x-panel-component col=12 panel="action">
                                <x-slot name="title">{{ $comVisualization->titulo ?? '' }}</x-slot>
                                <x-slot name="body">
                                    <div style="margin: 1%">{!! $comVisualization->texto_enriquecido ?? '' !!}</div>
                                    <div style="text-align: center;">{!! $comVisualization->codigo_incrustado ?? '' !!}</div>
                                    @if ($renderData)
                                        <x-data-visualization-component :data="$comVisualization->data" :chartType="$comVisualization->tipo_grafico" />
                                        @if ($comVisualization->muestra_tabla)
                                            <div class="table-responsive"
                                                style="margin:2%; border:#EAEAEA solid 1px; overflow-x:auto;">
                                                <table id="dataTable" class="table table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th>&nbsp;</th>
                                                            @foreach ($firstRow as $column)
                                                                <th class="text-nowrap" style="min-width: 100px;">
                                                                    {{ $column }}
                                                                </th>
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($rows as $row)
                                                            <tr>
                                                                @foreach ($row as $column)
                                                                    <td><span>{{ $column }}</span></td>
                                                                @endforeach
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    @endif
                                </x-slot>
                                <x-slot name="footer">
                                    @can('edit', app('App\Models\ComVisualization'))
                                        <a href="{{ route('voyager.visualizaciones.edit', $comVisualization->id ?? '') }}"
                                            class="btn btn-warning">
                                            <i class="glyphicon glyphicon-pencil"></i> Editar Visualización
                                        </a>
                                    @endcan
                                </x-slot>
                            </x-panel-component>
                        @else
                            @can('edit', app('App\Models\ComVisualization'))
                                <a href="{{ route('voyager.visualizaciones.edit', $comVisualization->id ?? '') }}"
                                    class="btn btn-warning pull-right">
                                    <i class="glyphicon glyphicon-pencil"></i> Editar Visualización
                                </a>
                            @endcan
                        @endif
                        {{-- FIN VISUALIZACION --}}

                        @foreach ($comIndicatorCutoff->comActions as $action)
                            <x-acciones.actions-table-component :action="$action" />
                        @endforeach
                    </div>
                @endforeach
            </div>
            @can('delete', $dataTypeContent)
                @include('voyager::indicadores-comges.partials.deletion-confirm-modal', [
                    'deletionPreview' => $deletionPreview ?? ['items' => [], 'total' => 0],
                ])
            @endcan

            @can('add', app('App\Models\ComIndicatorCutoff'))
                @php
                    $usedCutoffIds = $dataTypeContent->comIndicatorCutoffs->pluck('cutoff_id');
                    $tipoEvaluacion = $dataTypeContent->comCommitment->indicatorType->indicatorTypeSettings
                        ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
                        ->where('year_id', Helper::showCurrentYear()->id)
                        ->first()?->tipo_evaluacion;
                    // dd($tipoEvaluacion);
                    $availableCutoffs = \App\Models\Cutoff::whereNotIn('id', $usedCutoffIds)
                        ->where('habilitado', true)
                        ->when($tipoEvaluacion, fn($q) => $q->where('tipo_corte', $tipoEvaluacion))
                        ->get();
                @endphp
                <div class="modal fade" id="modal-duplicar-corte" tabindex="-1" role="dialog"
                    aria-labelledby="modal-duplicar-corte-label">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal"
                                    aria-label="Cerrar">&times;</button>
                                <h4 class="modal-title" id="modal-duplicar-corte-label">
                                    Duplicar pestaña: <strong id="modal-duplicar-nombre"></strong>
                                </h4>
                            </div>
                            <form id="form-duplicar-corte" method="POST" action="">
                                @csrf
                                <div class="modal-body">
                                    @if ($availableCutoffs->isEmpty())
                                        <p class="text-muted">No hay cortes disponibles para duplicar. Todos los cortes ya
                                            tienen una
                                            pestaña asignada.</p>
                                    @else
                                        <div class="form-group">
                                            <label for="select-cutoff-destino">Corte de destino</label>
                                            <select id="select-cutoff-destino" name="cutoff_id" class="form-control"
                                                required>
                                                <option value="">-- Seleccione un corte --</option>
                                                @foreach ($availableCutoffs as $availableCutoff)
                                                    <option value="{{ $availableCutoff->id }}">
                                                        {{ $availableCutoff->nombre_corte }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                                    @if ($availableCutoffs->isNotEmpty())
                                        <button type="submit" class="btn btn-warning">
                                            <i class="glyphicon glyphicon-copy"></i> Duplicar
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endcan
        </x-indicators.detail-tabs>
    </div>
@stop
@section('css')
    <link rel="stylesheet" href="{{ voyager_asset('lib/css/responsive.dataTables.min.css') }}">
@stop
@section('javascript')
    @if ($isModelTranslatable)
        <script>
            $(document).ready(function() {
                $('.side-body').multilingual();
            });
        </script>
    @endif
    <script>
        var deleteFormAction;
        $('.delete').on('click', function(e) {
            var form = $('#delete_form')[0];

            if (!deleteFormAction) {
                // Save form action initial value
                deleteFormAction = form.action;
            }

            form.action = deleteFormAction.match(/\/[0-9]+$/) ?
                deleteFormAction.replace(/([0-9]+$)/, $(this).data('id')) :
                deleteFormAction + '/' + $(this).data('id');

            $('#delete_modal').modal('show');
        });
    </script>
    <script>
        $('#modal-duplicar-corte').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var cutoffId = button.data('cutoff-id');
            var cutoffNombre = button.data('cutoff-nombre');
            $(this).find('#modal-duplicar-nombre').text(cutoffNombre);
            $(this).find('#form-duplicar-corte').attr(
                'action',
                '{{ route('duplicar-corte-comges', ':id') }}'.replace(':id', cutoffId)
            );
        });

        $('#form-duplicar-corte').on('submit', function(e) {
            var $btn = $(this).find('button[type="submit"]');
            if ($btn.prop('disabled')) {
                e.preventDefault();
                return;
            }
            $btn.prop('disabled', true).html('<i class="glyphicon glyphicon-refresh"></i> Duplicando...');
        });
    </script>
    @include('partials.voyager.bootstrap-modal-stack', [
        'modalIds' => ['#delete_modal', '#modal-duplicar-corte'],
    ])
@stop
