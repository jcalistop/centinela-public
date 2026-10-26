@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
    @include('partials.metas.metas-etapa-styles')
@stop

@section('page_title', ($indicatorType->metasLeyLabel() ? 'Indicadores ' . $indicatorType->metasLeyLabel() :
    $indicatorType->nombre_indicador . ' — Indicadores') . ': ' . $center->nombre_corto)

@section('page_header')
    <div class="metas-etapa-header">
        <h1 class="page-title">
            <i class="voyager-check"></i>
            @if ($indicatorType->metasLeyLabel())
                Indicadores {{ $indicatorType->metasLeyLabel() }} — {{ $center->nombre_corto }}
            @else
                Indicadores — {{ $center->nombre_corto }}
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

        <div class="clearfix">
            <x-metas.action-bar class="pull-left">
                @if (auth()->user()->hasRole(['control-gestion', 'admin']))
                    <a href="{{ route('metas.instrument.establishments.configure', $indicatorType) }}"
                        class="btn btn-success btn-sm">
                        <i class="voyager-angle-left"></i> Volver
                    </a>
                @else
                    <a href="{{ route('metas.instrument.index', $indicatorType) }}" class="btn btn-success btn-sm">
                        <i class="voyager-angle-left"></i> Volver al instrumento
                    </a>
                @endif
            </x-metas.action-bar>
        </div>

        <x-metas.compass-block>
            @livewire('metas-instrument.center-selection-component', [
                'indicatorTypeId' => $indicatorType->id,
                'instrumentNombre' => $indicatorType->nombre_indicador,
                'standaloneConfigPage' => true,
                'configCenterId' => $center->id,
            ])
        </x-metas.compass-block>
    </div>
@stop
