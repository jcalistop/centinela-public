@extends('voyager::master')

@section('page_title', 'Editar indicador')

@section('page_header')
    <x-metas.page-header icon="voyager-edit">
        Editar indicador — {{ $indicatorType->nombre_indicador }}
    </x-metas.page-header>
@stop

@section('content')
    <div class="page-content compass container-fluid metas-instrument-ui">
        @include('voyager::alerts')

        <x-metas.action-bar>
            <a href="{{ route('metas.instrument.indicators.show', [$indicatorType, $indicator]) }}"
                class="btn btn-success btn-sm">
                <i class="voyager-angle-left"></i> Volver a la ficha
            </a>
        </x-metas.action-bar>

        @livewire('metas-instrument.indicator-catalog-form-component', [
            'indicatorType' => $indicatorType,
            'indicator' => $indicator,
        ])
    </div>
@endsection

@section('javascript')
    @vite('resources/js/metas-objetivo-quill.js')
@stop
