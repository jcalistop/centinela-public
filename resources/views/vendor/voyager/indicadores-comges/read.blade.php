@php
    use Carbon\Carbon;
@endphp
@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
    @include('partials.indicators.comges-indicator-read-styles')
    <style>
        .indicator-container {
            display: grid;
            grid-template-columns: 2fr 10fr;
            margin: 1rem 0 1rem 0;
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
                    <span class="text-uppercase text-bold">Indicador {{ $dataTypeContent->clave }}<span></x-slot>
                <x-slot name="body">
                    <div class="indicator-container">
                        <span class="text-bold">Nombre:</span><span>{{ $dataTypeContent->nombre }}</span>
                        <span class="text-bold">Ámbitos:</span>
                        <span>
                            @php
                                $comAmbitos = $dataTypeContent->indicatorAmbitos ?? collect();
                            @endphp
                            @include('partials.indicators.ambitos-badges', [
                                'ambitos' => $comAmbitos,
                                'linkToDetail' => true,
                            ])
                        </span>
                        <span class="text-bold">Ponderación al
                            corte:</span><span>{{ $dataTypeContent->ponderacion_indicador }}%</span>
                        <span class="text-bold">Referentes:</span>
                        <span>
                            @php
                                $comIndicatorSetting = $dataTypeContent->comIndicatorSettings
                                    ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
                                    ->first();
                                $users = $comIndicatorSetting ? $comIndicatorSetting->users->unique() : collect();
                            @endphp
                            @forelse ($users as $user)
                                <a href="{{ route('voyager.users.show', $user->id) }}"
                                    class="label label-default metas-referente-label-link"
                                    style="font-weight:normal; margin-right:2px; display:inline-block;"
                                    title="Ver perfil y correo electrónico">
                                    <i class="voyager-person"></i> {{ $user->name }}
                                </a>
                            @empty
                                <span class="text-muted">Sin referentes asignados.</span>
                            @endforelse
                        </span>
                    </div>
                    <div class="indicator-container">
                        @if (!is_null(optional($comIndicatorSetting)->fecha_apertura) && !is_null(optional($comIndicatorSetting)->fecha_cierre))
                            <span
                                class="text-bold">Apertura:</span><span>{{ Carbon::createFromFormat('Y-m-d', $comIndicatorSetting->fecha_apertura)->format('d-m-Y') }}
                                08:00 horas</span>
                            <span
                                class="text-bold">Cierre:</span><span>{{ Carbon::createFromFormat('Y-m-d', $comIndicatorSetting->fecha_cierre)->format('d-m-Y') }}
                                17:00 horas</span>
                        @endif
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
                    <i class="voyager-plus"></i> Crear corte
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
                @endphp

                @foreach ($dataTypeContent->comIndicatorCutoffs as $comIndicatorCutoff)
                    <li class="@if ($comIndicatorCutoff->cutoff_id == $currentCut) active @endif">
                        <a data-toggle="tab" href="#{{ $comIndicatorCutoff->cutoff->id }}"><i
                                class="voyager-calendar"></i>{{ $comIndicatorCutoff->cutoff->clave }}</a>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                @foreach ($dataTypeContent->comIndicatorCutoffs as $comIndicatorCutoff)
                    <div role="tabpanel" class="tab-pane @if ($comIndicatorCutoff->cutoff_id == $currentCut) active @endif"
                        id="{{ $comIndicatorCutoff->cutoff->id }}">
                        @can('edit', app('App\Models\ComIndicatorCutoff'))
                            <a href="{{ route('voyager.cortes-comges.edit', $comIndicatorCutoff->id) }}" class="btn btn-info">
                                <i class="glyphicon glyphicon-pencil"></i> Editar
                                {{ $comIndicatorCutoff->cutoff->clave }}
                            </a>
                        @endcan
                        @if ($comIndicatorCutoff->comActions->count() == 0)
                            @can('delete', app('App\Models\ComIndicatorCutoff'))
                                <x-botones.delete-button-component table="com_indicator_cutoffs" :id="$comIndicatorCutoff->id"
                                    etiqueta="Eliminar {{ $comIndicatorCutoff->cutoff->clave }}" />
                            @endcan
                        @endif
                        @can('add', app('App\Models\ComAction'))
                            <a href="{{ route('voyager.acciones.create', ['com_indicator_cutoff_id' => $comIndicatorCutoff->id]) }}"
                                class="btn btn-success">
                                <i class="voyager-plus"></i> Crear Acción para {{ $comIndicatorCutoff->cutoff->clave }}
                            </a>
                        @endcan
                        @foreach ($comIndicatorCutoff->comActions as $action)
                            <x-acciones.actions-table-component :action="$action" />
                        @endforeach
                    </div>
                @endforeach
            </div>
        </x-indicators.detail-tabs>
        @can('delete', $dataTypeContent)
            @include('voyager::indicadores-comges.partials.deletion-confirm-modal', [
                'deletionPreview' => $deletionPreview ?? ['items' => [], 'total' => 0],
            ])
        @endcan
    </div>
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
    @include('partials.voyager.bootstrap-modal-stack', ['modalIds' => ['#delete_modal']])
@stop
