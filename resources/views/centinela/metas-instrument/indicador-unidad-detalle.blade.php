@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
@stop

@section('page_title', 'Carga mensual — ' . $indicator->clave . ' ' . $indicator->nombre)

@section('page_header')
    <x-metas.page-header icon="voyager-edit">
        Carga mensual — <code>{{ $indicator->clave }}</code> {{ $indicator->nombre }}
    </x-metas.page-header>
@stop

@section('content')
    <div class="page-content compass container-fluid metas-instrument-ui">
        @include('voyager::alerts')

        <x-metas.action-bar>
            <a href="{{ route('metas.instrument.indicador.center.units', ['indicatorType' => $indicatorType, 'indicator' => $indicator, 'center' => $center]) }}"
                class="btn btn-success btn-sm">
                <i class="voyager-angle-left"></i> Volver a unidades
            </a>
            <x-metas.instrument-hub-button :indicator-type="$indicatorType" />
        </x-metas.action-bar>

        <x-metas.bordered-panel>
            <x-slot:heading>
                <i class="voyager-hospital"></i> {{ $center->nombre_corto }} —
                <span class="label label-info">{{ $measurementUnit->codigo }}</span>
                {{ $measurementUnit->nombre ? ' — ' . $measurementUnit->nombre : '' }}
            </x-slot:heading>
            @livewire('metas-instrument.monthly-data-entry-component', [
                'indicatorId' => $indicator->id,
                'centerId' => $center->id,
                'indicatorTypeId' => $indicatorType->id,
                'measurementUnitId' => $measurementUnit->id,
            ])
        </x-metas.bordered-panel>
    </div>
@stop
