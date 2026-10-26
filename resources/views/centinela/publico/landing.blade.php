@extends('centinela.publico.layout')

@section('title', 'Paneles de información — Centinela')

@section('content')
    <div class="cen-publico__section" style="margin-bottom:1.25rem;">
        <div class="cen-publico__invite-heading">
            <h1 style="margin:0;font-size:1.4rem;color:#1a3a5c;">Paneles de información nacional</h1>
            @include('centinela.publico.partials.invitar-publicar-panel')
        </div>
        <p class="cen-publico__empty" style="margin:0.5rem 0 0;">
            Resumenes a nivel país, seleccione un panel para ver el informe y descargar los datos.
        </p>
    </div>

    @if ($panels->count() === 0)
        <p class="cen-publico__empty">No hay paneles publicados.</p>
    @else
        <div class="cen-publico__grid">
            @foreach ($panels as $panel)
                <a class="cen-publico__card" href="{{ route('centinela.publico.panel', ['slug' => $panel->slug]) }}">
                    <img src="{{ $panel->previewUrl() }}" alt="{{ $panel->titulo }}">
                    <span>{{ $panel->titulo }}</span>
                    @if (filled($panel->version_panel))
                        <span class="cen-publico__card-version">versión {{ $panel->version_panel }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        @if ($panels->hasPages())
            <div style="margin-top:1.25rem;">
                {{ $panels->links() }}
            </div>
        @endif
    @endif

    @if ($ssEnabled)
        <div class="cen-publico__section" style="margin-top:1.5rem;margin-bottom:1.25rem;">
            <h2 style="margin:0 0 0.5rem;font-size:1.15rem;color:#1a3a5c;">Servicios de Salud</h2>
            <p class="cen-publico__empty" style="margin:0;">
                Seleccione un Servicio de Salud para ver el resumen de los instrumentos de evaluación.
            </p>
        </div>

        @if (count($services) === 0)
            <p class="cen-publico__empty">No hay Servicios de Salud habilitados.</p>
        @else
            <div class="cen-publico__grid">
                @foreach ($services as $service)
                    <a class="cen-publico__card" href="{{ route('centinela.publico.ss', ['ss' => $service['id']]) }}">
                        <img src="{{ $service['logo_url'] }}" alt="{{ $service['nombre'] }}">
                        <span>{{ $service['nombre'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
@endsection
