@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
    <style>
        .metas-etapa-header {
            position: relative;
        }

        .metas-etapa-badge {
            position: absolute;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            font-size: 14px;
            font-weight: 600;
            line-height: 1.35;
            padding: 0.4em 0.7em;
            z-index: 1;
        }
    </style>
@stop

@section('page_title', $indicatorType->nombre_indicador)

@section('page_header')
    <div class="metas-etapa-header">
        <h1 class="page-title">
            <i class="{{ $indicatorType->icon_class }}"></i>
            {{ $indicatorType->nombre_indicador }}
        </h1>
        @if ($setting->etapa_vigente)
            <span class="label label-warning metas-etapa-badge">
                <i class="voyager-activity"></i> Etapa vigente: {{ $setting->etapa_vigente }}
            </span>
        @endif
    </div>
@stop

@section('content')
    <div class="page-content compass container-fluid">
        @include('voyager::alerts')

        @if (session('flash_adp_settings'))
            <div class="alert alert-success">{{ session('flash_adp_settings') }}</div>
        @endif

        <div class="row">
            <div class="col-md-12">
                @php
                    $activeTab = 'autoevaluacion';
                    if (in_array($roleName, ['admin', 'control-gestion', 'parametrizador'], true)) {
                        $activeTab = 'configuracion';
                    }
                @endphp

                <ul class="nav nav-tabs" role="tablist" style="margin-bottom:0">
                    @if (in_array($roleName, ['admin', 'control-gestion', 'parametrizador'], true))
                        <li role="presentation" class="{{ $activeTab === 'configuracion' ? 'active' : '' }}">
                            <a href="#tab-configuracion" role="tab" data-toggle="tab">
                                <i class="voyager-settings"></i> Configuración
                            </a>
                        </li>
                    @endif

                    @if (in_array($roleName, ['admin', 'parametrizador'], true))
                        <li role="presentation" class="{{ $activeTab === 'cargos' ? 'active' : '' }}">
                            <a href="#tab-cargos" role="tab" data-toggle="tab">
                                <i class="voyager-group"></i> Cargos
                            </a>
                        </li>
                    @endif

                    @if (in_array($roleName, ['admin', 'control-gestion'], true))
                        <li role="presentation" class="{{ $activeTab === 'asignaciones' ? 'active' : '' }}">
                            <a href="#tab-asignaciones" role="tab" data-toggle="tab">
                                <i class="voyager-people"></i> Asignaciones
                            </a>
                        </li>
                    @endif

                    @if (in_array($roleName, ['admin', 'control-gestion', 'parametrizador'], true))
                        <li role="presentation" class="{{ $activeTab === 'catalogo' ? 'active' : '' }}">
                            <a href="#tab-catalogo" role="tab" data-toggle="tab">
                                <i class="voyager-list"></i> Catálogo de indicadores
                            </a>
                        </li>
                    @endif

                    @if (in_array($roleName, ['admin', 'control-gestion'], true))
                        <li role="presentation" class="{{ $activeTab === 'parametrizacion' ? 'active' : '' }}">
                            <a href="#tab-parametrizacion" role="tab" data-toggle="tab">
                                <i class="voyager-list"></i> Parametrización
                            </a>
                        </li>
                    @endif

                    <li role="presentation" class="{{ $activeTab === 'autoevaluacion' ? 'active' : '' }}">
                        <a href="#tab-autoevaluacion" role="tab" data-toggle="tab">
                            <i class="voyager-activity"></i> Autoevaluación
                        </a>
                    </li>

                    <li role="presentation">
                        <a href="#tab-documentos" role="tab" data-toggle="tab">
                            <i class="voyager-book"></i> Documentos
                        </a>
                    </li>
                </ul>

                <div class="tab-content" style="padding-top:20px">
                    @if (in_array($roleName, ['admin', 'control-gestion', 'parametrizador'], true))
                        <div role="tabpanel" class="tab-pane {{ $activeTab === 'configuracion' ? 'active' : '' }}"
                            id="tab-configuracion">
                            <x-panel-component col="12" panel="warning">
                                <x-slot name="title">
                                    <i class="voyager-settings"></i> Configuración del instrumento
                                </x-slot>
                                <x-slot name="body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="panel panel-bordered">
                                                <div class="panel-heading">
                                                    <p class="panel-title">
                                                        <i class="voyager-activity"></i> Etapa vigente del instrumento
                                                    </p>
                                                </div>
                                                <div class="panel-body">
                                                    @if ($setting->etapa_vigente)
                                                        <span class="label label-warning">
                                                            {{ $setting->etapa_vigente }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="panel panel-bordered">
                                                <div class="panel-heading">
                                                    <p class="panel-title">
                                                        <i class="voyager-power"></i> Habilitación para su Servicio de
                                                        Salud
                                                    </p>
                                                </div>
                                                <div class="panel-body">
                                                    <p class="m-b-10">
                                                        <strong>Estado:</strong>
                                                        {{ $setting->habilitado ? 'Habilitado' : 'No habilitado' }}
                                                    </p>

                                                    @if (in_array($roleName, ['admin', 'control-gestion'], true))
                                                        <form method="post"
                                                            action="{{ route('adp.instrument.settings.toggle') }}"
                                                            onsubmit="return confirm('¿Cambiar habilitación del instrumento para su SS?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-warning">
                                                                {{ $setting->habilitado ? 'Deshabilitar' : 'Habilitar' }}
                                                            </button>
                                                        </form>
                                                    @else
                                                        <p class="text-muted m-b-0">Solo <code>control-gestion</code> o
                                                            <code>admin</code> pueden cambiar la habilitación.
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </x-slot>
                            </x-panel-component>
                        </div>
                    @endif

                    @if (in_array($roleName, ['admin', 'parametrizador'], true))
                        <div role="tabpanel" class="tab-pane {{ $activeTab === 'cargos' ? 'active' : '' }}"
                            id="tab-cargos">
                            @livewire('adp.catalog.position-component', key('adp-pos-tab'))
                        </div>
                    @endif

                    @if (in_array($roleName, ['admin', 'control-gestion'], true))
                        <div role="tabpanel" class="tab-pane {{ $activeTab === 'asignaciones' ? 'active' : '' }}"
                            id="tab-asignaciones">
                            @livewire('adp.assignment-component', key('adp-assignment-tab'))
                        </div>
                    @endif

                    @if (in_array($roleName, ['admin', 'control-gestion', 'parametrizador'], true))
                        <div role="tabpanel" class="tab-pane {{ $activeTab === 'catalogo' ? 'active' : '' }}"
                            id="tab-catalogo">
                            @livewire('adp.catalog.indicator-catalog-component', key('adp-catalog-tab'))
                        </div>
                    @endif

                    @if (in_array($roleName, ['admin', 'control-gestion'], true))
                        <div role="tabpanel" class="tab-pane {{ $activeTab === 'parametrizacion' ? 'active' : '' }}"
                            id="tab-parametrizacion">
                            @livewire('adp.agreement-parametrization-component', key('adp-param-tab'))
                        </div>
                    @endif

                    <div role="tabpanel" class="tab-pane {{ $activeTab === 'autoevaluacion' ? 'active' : '' }}"
                        id="tab-autoevaluacion">
                        <p>
                            <a href="{{ route('adp.self_evaluation.agreements') }}" class="btn btn-primary">Ir a
                                autoevaluación</a>
                        </p>
                    </div>

                    <div role="tabpanel" class="tab-pane" id="tab-documentos">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <p class="panel-title">
                                    Documentación normativa —
                                    {{ Helper::showCurrentYear()->ano }}</p>
                            </div>
                            <div class="panel-body">
                                @include('partials.documents.tab-add-document-button', [
                                    'hub' => 'adp',
                                    'indicatorTypeId' => $indicatorType->id,
                                    'metasRoute' => null,
                                ])
                                <x-browse-view-component table="documents" :scopeWhere="['indicator_type_id' => $indicatorType->id]" :documentHubContext="['hub' => 'adp', 'indicator_type_id' => $indicatorType->id]" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
