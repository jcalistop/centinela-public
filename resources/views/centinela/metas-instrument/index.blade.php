@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
    @include('partials.metas.metas-etapa-styles')
    @include('partials.metas.metas-print-styles')
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
                <i class="voyager-activity"></i> Etapa vigente: {{ $setting->etapa_vigente->label() }}
            </span>
        @endif
    </div>
@stop

@section('content')
    <div class="page-content compass container-fluid metas-instrument-ui">
        @include('voyager::alerts')

        @php
            $activeTab = 'catalogo';
        @endphp

        {{-- Navegación de pestañas --}}
        <ul class="nav nav-tabs metas-nav-tabs metas-hub-nav-tabs" role="tablist">
            <li role="presentation" class="{{ $activeTab === 'catalogo' ? 'active' : '' }}">
                <a href="#tab-catalogo" role="tab" data-toggle="tab">
                    <i class="voyager-list"></i> Catálogo
                </a>
            </li>

            @if ($roleName !== 'parametrizador')
                <li role="presentation" class="{{ $activeTab === 'resultados' ? 'active' : '' }}">
                    <a href="#tab-resultados" role="tab" data-toggle="tab">
                        <i class="voyager-bar-chart"></i> Resultados
                    </a>
                </li>
            @endif

            <li role="presentation">
                <a href="#tab-documentos" role="tab" data-toggle="tab">
                    <i class="voyager-book"></i> Documentos
                </a>
            </li>

            @if (in_array($roleName, ['admin', 'parametrizador', 'control-gestion']))
                <li role="presentation" class="{{ $activeTab === 'configuracion' ? 'active' : '' }}">
                    <a href="#tab-configuracion" role="tab" data-toggle="tab">
                        <i class="voyager-settings"></i> Configuración
                    </a>
                </li>
            @endif
        </ul>

        <div class="tab-content metas-tab-content metas-hub-tab-content">

            {{-- Pestaña: Catálogo (todos los roles) --}}
            <div role="tabpanel" class="tab-pane metas-tab-panel {{ $activeTab === 'catalogo' ? 'active' : '' }}"
                id="tab-catalogo">
                <x-metas.compass-block :title="'Catálogo — ' . session('indicator_year')->ano" icon="voyager-list">
                    @livewire('metas-instrument.indicator-catalog-component', ['indicatorTypeId' => $indicatorType->id])
                </x-metas.compass-block>
            </div>

            {{-- Pestaña: Resultados (todos los roles) --}}
            <div role="tabpanel" class="tab-pane metas-tab-panel {{ $activeTab === 'resultados' ? 'active' : '' }}"
                id="tab-resultados">
                <x-metas.compass-block :title="'Resultados — ' . session('indicator_year')->ano" icon="voyager-bar-chart">
                    @livewire('metas-instrument.results-summary-component', ['indicatorTypeId' => $indicatorType->id])
                </x-metas.compass-block>
            </div>

            {{-- Pestaña: Documentos (mismo patrón que COMGES; filtrado por instrumento — SDD-034) --}}
            <div role="tabpanel" class="tab-pane metas-tab-panel" id="tab-documentos">
                <x-metas.compass-block :title="'Documentos — ' . session('indicator_year')->ano" icon="voyager-book">
                    @include('partials.documents.tab-add-document-button', [
                        'hub' => 'metas',
                        'indicatorTypeId' => $indicatorType->id,
                        'metasRoute' => $indicatorType->route,
                    ])
                    <x-browse-view-component table="documents" :scopeWhere="['indicator_type_id' => $indicatorType->id]" :documentHubContext="['hub' => 'metas', 'metas_route' => $indicatorType->route]" />
                </x-metas.compass-block>
            </div>

            {{-- Pestaña: Configuración (admin / parametrizador / control-gestion) --}}
            @if (in_array($roleName, ['admin', 'parametrizador', 'control-gestion']))
                <div role="tabpanel" class="tab-pane metas-tab-panel {{ $activeTab === 'configuracion' ? 'active' : '' }}"
                    id="tab-configuracion">
                    <x-metas.compass-block :title="'Configuración — ' . session('indicator_year')->ano" icon="voyager-settings">
                        @livewire('metas-instrument.instrument-settings-component', ['indicatorTypeId' => $indicatorType->id])
                    </x-metas.compass-block>
                </div>
            @endif

        </div>{{-- /.tab-content --}}
    </div>

    @include('partials.metas.indicator-data-modal')
@stop

@push('javascript')
    @include('partials.metas.metas-copy-image-scripts')
    @include('livewire.metas-instrument.partials.indicator-data-modal-scripts', [
        'attachDownloadGuardsOnLoad' => false,
    ])
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var hash = window.location.hash;
            if (!hash) {
                return;
            }

            var tabLink = document.querySelector('.nav-tabs a[href="' + hash + '"]');
            if (tabLink && typeof jQuery !== 'undefined') {
                jQuery(tabLink).tab('show');
            }
        });
    </script>
@endpush
