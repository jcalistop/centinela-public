@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
    @include('partials.metas.metas-etapa-styles')
@stop

@section('page_title', $indicatorType->nombre_indicador . ' — Configurar establecimientos')

@section('page_header')
    <div class="metas-etapa-header">
        <h1 class="page-title">
            <i class="voyager-company"></i>
            Configurar establecimientos — {{ $indicatorType->nombre_indicador }}
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

        <div class="metas-establishment-results-toolbar no-print" style="margin-bottom: 1rem;">
            <a href="{{ route('metas.instrument.index', $indicatorType) }}#tab-resultados" class="btn btn-success btn-sm">
                <i class="voyager-angle-left"></i> Volver a Resultados
            </a>
        </div>

        <x-metas.compass-block>
            @livewire('metas-instrument.center-selection-component', [
                'indicatorTypeId' => $indicatorType->id,
                'instrumentNombre' => $indicatorType->nombre_indicador,
            ])
        </x-metas.compass-block>
    </div>
@stop
