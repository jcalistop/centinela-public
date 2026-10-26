@extends('voyager::master')

@section('page_title', 'Nuevo indicador')

@section('page_header')
    <x-metas.page-header icon="voyager-plus">
        Nuevo indicador — {{ $indicatorType->nombre_indicador }}
    </x-metas.page-header>
@stop

@section('content')
    <div class="page-content compass container-fluid metas-instrument-ui">
        @include('voyager::alerts')

        <x-metas.action-bar>
            <a href="{{ route('metas.instrument.index', $indicatorType) }}" class="btn btn-success btn-sm">
                <i class="voyager-angle-left"></i> Volver al catálogo
            </a>
        </x-metas.action-bar>

        @livewire('metas-instrument.indicator-catalog-form-component', ['indicatorType' => $indicatorType])
    </div>
@endsection

@section('javascript')
    @vite('resources/js/metas-objetivo-quill.js')
@stop
