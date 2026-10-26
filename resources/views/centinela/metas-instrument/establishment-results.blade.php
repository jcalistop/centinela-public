@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
    @include('partials.metas.metas-etapa-styles')
    @include('partials.metas.metas-establishment-results-styles')
    @include('partials.metas.metas-print-styles')
@stop

@section('page_title', $indicatorType->nombre_indicador . ' — Resultados: ' . $center->nombre_corto .
    (isset($measurementUnit) && $measurementUnit ? ' / ' . $measurementUnit->codigo : ''))

@section('page_header')
    <div class="metas-etapa-header">
        <h1 class="page-title">
            <i class="{{ $indicatorType->icon_class }}"></i>
            {{ $indicatorType->nombre_indicador }} para el establecimiento {{ $center->nombre_corto }}
            ({{ $center->codigo }})
            @if (isset($measurementUnit) && $measurementUnit)
                <span class="metas-results-unit-header-context">
                    — Unidad <code>{{ $measurementUnit->codigo }}</code>
                    @if ($measurementUnit->nombre)
                        ({{ $measurementUnit->nombre }})
                    @endif
                </span>
            @endif
        </h1>
        @if ($setting->etapa_vigente)
            <span class="label label-warning metas-etapa-badge">
                <i class="voyager-activity"></i> Etapa vigente: {{ $setting->etapa_vigente }}
            </span>
        @endif
    </div>
@stop

@section('content')
    <div class="page-content compass container-fluid metas-instrument-ui">
        @include('voyager::alerts')

        <div class="metas-establishment-results-toolbar no-print">
            <a href="{{ route('metas.instrument.index', $indicatorType) }}#tab-resultados" class="btn btn-success btn-sm">
                <i class="voyager-angle-left"></i> Volver al catálogo de indicadores
            </a>
        </div>

        @php
            $activeEtapaTab = match ($setting->etapa_vigente?->value ?? 'AUTOEVALUACION') {
                'PRELIMINAR' => 'preliminar',
                'APELACION' => 'apelacion',
                'FINAL' => 'final',
                default => 'autoevaluacion',
            };
        @endphp

        <ul class="nav nav-tabs metas-nav-tabs" role="tablist">
            <li role="presentation" class="{{ $activeEtapaTab === 'autoevaluacion' ? 'active' : '' }}">
                <a href="#tab-etapa-autoevaluacion" role="tab" data-toggle="tab">
                    <i class="voyager-activity"></i> Autoevaluación
                </a>
            </li>
            <li role="presentation" class="{{ $activeEtapaTab === 'preliminar' ? 'active' : '' }}">
                <a href="#tab-etapa-preliminar" role="tab" data-toggle="tab">
                    <i class="voyager-edit"></i> Evaluación Preliminar
                </a>
            </li>
            <li role="presentation" class="{{ $activeEtapaTab === 'apelacion' ? 'active' : '' }}">
                <a href="#tab-etapa-apelacion" role="tab" data-toggle="tab">
                    <i class="voyager-warning"></i> Apelación
                </a>
            </li>
            <li role="presentation" class="{{ $activeEtapaTab === 'final' ? 'active' : '' }}">
                <a href="#tab-etapa-final" role="tab" data-toggle="tab">
                    <i class="voyager-check"></i> Evaluación Final
                </a>
            </li>
        </ul>

        <div class="tab-content metas-tab-content">
            <div role="tabpanel" class="tab-pane {{ $activeEtapaTab === 'autoevaluacion' ? 'active' : '' }}"
                id="tab-etapa-autoevaluacion">
                @livewire('metas-instrument.establishment-results-detail-component', [
                    'indicatorTypeId' => $indicatorType->id,
                    'centerId' => $center->id,
                ])
            </div>

            <div role="tabpanel" class="tab-pane {{ $activeEtapaTab === 'preliminar' ? 'active' : '' }}"
                id="tab-etapa-preliminar">
                @livewire('metas-instrument.preliminary-evaluation-component', [
                    'indicatorTypeId' => $indicatorType->id,
                    'centerId' => $center->id,
                ])
            </div>

            <div role="tabpanel" class="tab-pane {{ $activeEtapaTab === 'apelacion' ? 'active' : '' }}"
                id="tab-etapa-apelacion">
                @livewire('metas-instrument.indicator-appeal-component', [
                    'indicatorTypeId' => $indicatorType->id,
                    'centerId' => $center->id,
                ])
            </div>

            <div role="tabpanel" class="tab-pane {{ $activeEtapaTab === 'final' ? 'active' : '' }}" id="tab-etapa-final">
                @livewire('metas-instrument.final-evaluation-component', [
                    'indicatorTypeId' => $indicatorType->id,
                    'centerId' => $center->id,
                ])
            </div>
        </div>
    </div>

    @include('partials.metas.indicator-data-modal')
@stop

@push('javascript')
    @include('partials.metas.metas-copy-image-scripts')
    @include('partials.metas.metas-tooltip-scripts')
    @include('livewire.metas-instrument.partials.indicator-data-modal-scripts', [
        'attachDownloadGuardsOnLoad' => true,
    ])
@endpush
