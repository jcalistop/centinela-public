@extends('voyager::master')

@section('css')
    @include('centinela.support.partials.compass-shell')
@stop

@section('page_title', 'Soporte — Receptores')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-person"></i> Receptores de notificacion
    </h1>
@stop

@section('content')
    <div class="page-content compass container-fluid support-compass cen-compass-ui support-module">
        @include('voyager::alerts')

        @if (session('flash_support_success'))
            <div class="alert alert-success">{{ session('flash_support_success') }}</div>
        @endif

        <p class="support-page-actions">
            @include('centinela.support.partials.back-link', [
                'href' => route('centinela.support.index'),
                'label' => 'Bandeja Soporte',
            ])
            <a class="btn btn-sm btn-primary" href="{{ route('centinela.support.receivers.create') }}">
                <i class="voyager-plus" aria-hidden="true"></i> Nuevo receptor
            </a>
        </p>

        <x-centinela.support.compass-block title="Receptores configurados" icon="voyager-people">
            @if ($receivers->isEmpty())
                <div class="support-empty-state">
                    <span class="support-empty-icon voyager-person"></span>
                    <p>No hay receptores configurados.</p>
                    <a href="{{ route('centinela.support.receivers.create') }}" class="btn btn-sm btn-primary">
                        <i class="voyager-plus" aria-hidden="true"></i> Configurar primer receptor
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-striped cen-compass-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Ambito</th>
                                <th>SS</th>
                                <th>Primario</th>
                                <th>Activo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($receivers as $r)
                                <tr>
                                    <td data-label="ID">{{ $r->id }}</td>
                                    <td data-label="Usuario">{{ $r->user?->name }}</td>
                                    <td data-label="Ambito">
                                        <span
                                            class="support-receiver-scope-badge support-receiver-scope-badge--{{ $r->scope === 'global' ? 'global' : 'ss' }}">
                                            @if ($r->scope === 'global')
                                                Soporte tecnico (alcance global)
                                            @else
                                                Gestion
                                            @endif
                                        </span>
                                    </td>
                                    <td data-label="SS">
                                        {{ $r->scope === 'global' ? '—' : $r->cenHieDependency?->nombre ?? '—' }}</td>
                                    <td data-label="Primario">
                                        @if ($r->is_primary)
                                            <span class="support-star-primary" title="Receptor primario">&starf;</span>
                                        @else
                                            &mdash;
                                        @endif
                                    </td>
                                    <td data-label="Activo">
                                        @if ($r->enabled)
                                            <span class="support-check-active" title="Habilitado">&#10003;</span>
                                        @else
                                            <span class="support-cross-inactive" title="Deshabilitado">&#10007;</span>
                                        @endif
                                    </td>
                                    <td data-label="Acciones">
                                        <a class="btn btn-xs btn-success"
                                            href="{{ route('centinela.support.receivers.edit', $r) }}">
                                            <i class="voyager-edit" aria-hidden="true"></i> Editar
                                        </a>
                                        <form method="post" action="{{ route('centinela.support.receivers.destroy', $r) }}"
                                            style="display:inline;" onsubmit="return confirm('Eliminar?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-danger">
                                                <i class="voyager-trash" aria-hidden="true"></i> Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-centinela.support.compass-block>
    </div>
@stop
