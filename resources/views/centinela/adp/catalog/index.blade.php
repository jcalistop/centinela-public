@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
@stop

@section('page_title', 'ADP — Catálogo de indicadores (por SS)')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-list"></i>
        Catálogo de indicadores ADP (por Servicio de Salud)
    </h1>
@stop

@section('content')
    <div class="page-content compass container-fluid">
        @include('voyager::alerts')

        <p>
            <a href="{{ route('adp.instrument.index') }}">← Hub ADP</a>
        </p>

        @if (session('flash_catalog'))
            <div class="alert alert-success">{{ session('flash_catalog') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="m-b-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="panel panel-bordered m-t-20">
            <div class="panel-heading">
                <p class="panel-title">Ítems</p>
                <div class="panel-actions">
                    <a class="btn btn-sm btn-primary" href="{{ route('adp.catalog.create') }}">Crear ítem</a>
                </div>
            </div>
            <div class="panel-body">
                @if ($items->isEmpty())
                    <p class="text-muted">No hay ítems en el catálogo para tu Servicio de Salud.</p>
                @else
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Clave</th>
                                <th>Nombre</th>
                                <th>Método</th>
                                <th>Estado</th>
                                <th>Fórmula</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $it)
                                <tr>
                                    <td>{{ $it->clave }}</td>
                                    <td>{{ $it->nombre }}</td>
                                    <td>{{ $it->calculation_method }}</td>
                                    <td>{{ $it->estado ? 'Activo' : 'Inactivo' }}</td>
                                    <td>{{ $it->formula ? 'Sí' : 'No' }}</td>
                                    <td>
                                        <a class="btn btn-xs btn-default"
                                            href="{{ route('adp.catalog.edit', $it) }}">Editar</a>
                                        <form method="post" action="{{ route('adp.catalog.destroy', $it) }}"
                                            style="display:inline" onsubmit="return confirm('¿Eliminar ítem?');">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn btn-xs btn-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@stop
