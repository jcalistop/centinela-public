@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
@stop

@section('page_title', 'Carga de datos — ' . $indicator->clave)

@section('page_header')
    <x-metas.page-header icon="voyager-upload">
        Carga de datos — <code>{{ $indicator->clave }}</code> {{ $indicator->nombre }}
    </x-metas.page-header>
@stop

@section('content')
    @php
        $showConfigTab = auth()
            ->user()
            ->hasRole(['control-gestion', 'admin']);
        $activeTab = 'carga';

        if (
            $showConfigTab &&
            $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_COMPLETE &&
            $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_NO_FIELDS
        ) {
            $activeTab = 'tipos-entrada';
        }

        $hasHubAlerts =
            (!$showConfigTab &&
                $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_COMPLETE &&
                $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_NO_FIELDS) ||
            !empty($formulaIssue);
    @endphp

    <div class="page-content compass container-fluid metas-instrument-ui">
        @include('voyager::alerts')

        <x-metas.action-bar class="metas-carga-hub-action-bar">
            <div class="metas-carga-hub-action-bar__primary">
                <a href="{{ route('metas.instrument.indicators.show', [$indicatorType, $indicator]) }}#tab-desempeno"
                    class="btn btn-success btn-sm">
                    <i class="voyager-angle-left"></i> Volver al indicador
                </a>
            </div>
            @if ($canDeleteLoadedData ?? false)
                <div class="metas-carga-hub-action-bar__secondary">
                    @if ($hasLoadedData)
                        <form method="POST"
                            action="{{ route('metas.instrument.indicador.datos.destroy', ['indicatorType' => $indicatorType, 'indicator' => $indicator]) }}"
                            class="metas-inline-form"
                            onsubmit="return confirm('¿Eliminar todos los datos cargados de este indicador en su Servicio de Salud? Esta acción no se puede deshacer.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="voyager-trash"></i> Eliminar datos
                            </button>
                        </form>
                    @else
                        <button class="btn btn-default btn-sm" disabled title="No hay datos cargados para este indicador">
                            <i class="voyager-trash"></i> Eliminar datos
                        </button>
                    @endif
                </div>
            @endif
        </x-metas.action-bar>

        @if ($hasHubAlerts)
            <div class="metas-carga-hub-alerts" role="group" aria-label="Alertas del indicador">
                @if (
                    !$showConfigTab &&
                        $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_COMPLETE &&
                        $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_NO_FIELDS)
                    <x-metas.field-ss-config-pending-alert variant="compact" :status="$fieldSsConfigStatus"
                        class="metas-carga-hub-alerts__item" />
                @endif

                @if (!empty($formulaIssue))
                    <x-metas.formula-issue-alert variant="compact" :message="$formulaIssue['message']" class="metas-carga-hub-alerts__item" />
                @endif
            </div>
        @endif

        @if ($showConfigTab)
            <ul class="nav nav-tabs metas-nav-tabs metas-carga-hub-tabs" role="tablist">
                <li role="presentation" class="{{ $activeTab === 'carga' ? 'active' : '' }}">
                    <a href="#tab-carga-datos" role="tab" data-toggle="tab">
                        <i class="voyager-upload"></i> Carga de datos
                    </a>
                </li>
                <li role="presentation" class="{{ $activeTab === 'tipos-entrada' ? 'active' : '' }}">
                    <a href="#tab-tipos-entrada" role="tab" data-toggle="tab">
                        <i class="voyager-settings"></i> Tipos de entrada y agregación
                        @if (
                            $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_COMPLETE &&
                                $fieldSsConfigStatus !== \App\Services\Metas\MetFieldSsConfigService::STATUS_NO_FIELDS)
                            <span class="glyphicon glyphicon-exclamation-sign text-warning" aria-hidden="true"
                                title="Configuración pendiente"></span>
                        @endif
                    </a>
                </li>
            </ul>

            <div class="tab-content metas-tab-content metas-carga-hub-tab-content">
                <div role="tabpanel" class="tab-pane {{ $activeTab === 'carga' ? 'active' : '' }}" id="tab-carga-datos">
                    @if ($allowsDataEntryHub)
                        @include('centinela.metas-instrument.partials.indicador-carga-datos')
                    @else
                        <p class="metas-carga-hub-access-notice text-muted">
                            <i class="voyager-lock"></i> La configuración de tipos de entrada debe estar completa antes
                            de acceder al listado de establecimientos.
                        </p>
                    @endif
                </div>

                <div role="tabpanel"
                    class="tab-pane metas-carga-hub-tab-pane {{ $activeTab === 'tipos-entrada' ? 'active' : '' }}"
                    id="tab-tipos-entrada">
                    @livewire('metas-instrument.indicator-field-ss-config-component', [
                        'indicatorId' => $indicator->id,
                        'indicatorTypeId' => $indicatorType->id,
                    ])
                </div>
            </div>
        @elseif ($allowsDataEntryHub)
            @include('centinela.metas-instrument.partials.indicador-carga-datos')
        @endif
    </div>
@stop

@push('javascript')
    <script>
        (function() {
            @include('partials.download-guard')

            document.addEventListener('DOMContentLoaded', function() {
                attachCentinelaDownloadGuards(document.querySelector('.page-content'));

                var hash = window.location.hash;
                if (!hash) {
                    return;
                }

                var tabLink = document.querySelector('.nav-tabs a[href="' + hash + '"]');
                if (tabLink && typeof jQuery !== 'undefined') {
                    jQuery(tabLink).tab('show');
                }
            });
        })();
    </script>
@endpush
