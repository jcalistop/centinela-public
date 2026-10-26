@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
@stop

@section('page_title', 'Carga mensual por unidad — ' . $indicator->clave . ' ' . $indicator->nombre)

@section('page_header')
    <x-metas.page-header icon="voyager-upload">
        Carga mensual por unidad — <code>{{ $indicator->clave }}</code> {{ $indicator->nombre }}
    </x-metas.page-header>
@stop

@section('content')
    <div class="page-content compass container-fluid metas-instrument-ui">
        @include('voyager::alerts')

        <x-metas.action-bar>
            <a href="{{ route('metas.instrument.indicador.carga', ['indicatorType' => $indicatorType, 'indicator' => $indicator]) }}"
                class="btn btn-success btn-sm">
                <i class="voyager-angle-left"></i> Volver a carga del indicador
            </a>
            <x-metas.instrument-hub-button :indicator-type="$indicatorType" />
        </x-metas.action-bar>

        <x-centinela.compass.dashboard-block :title="$center->nombre_corto . ' — ' . $indicator->clave" icon="voyager-hospital">
            @if ($units->isEmpty())
                <div class="alert alert-info metas-carga-alert--flush">
                    No hay unidades activas para este establecimiento. Cree unidades desde
                    <strong>Resultados → Configurar establecimientos</strong> (columna <strong>Unidades</strong>).
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped metas-instrument-table metas-units-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th class="text-center metas-units-col--accion">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($units as $u)
                                <tr>
                                    <td><code>{{ $u->codigo }}</code></td>
                                    <td>{{ $u->nombre ?? '—' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('metas.instrument.indicador.showUnit', ['indicatorType' => $indicatorType, 'indicator' => $indicator, 'center' => $center, 'measurementUnit' => $u]) }}"
                                            class="btn btn-xs btn-primary">
                                            <i class="voyager-edit"></i> Cargar datos
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-centinela.compass.dashboard-block>
    </div>
@stop
