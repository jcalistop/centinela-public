@extends('centinela.publico.layout')

@section('title', $panel->titulo . ' — Centinela')

@section('main_class', 'cen-publico__main--wide')

@section('content')
    <a class="cen-publico__back" href="{{ route('centinela.publico.landing') }}">← Volver a paneles</a>

    @if (!empty($isPreview))
        <p role="status"
            style="margin:0 0 1rem;padding:0.65rem 0.9rem;border-radius:0.35rem;background:#fff8e6;border:1px solid #e6d9a8;color:#5c4d1a;font-size:0.95rem;">
            Vista de <strong>revisión</strong> — este panel aún no está publicado en el dashboard público.
        </p>
    @endif

    <div
        style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;">
        <div style="min-width:0;flex:1 1 16rem;">
            <h1 style="margin:0 0 0.35rem;font-size:1.35rem;font-weight:600;color:#1a3a5c;">
                {{ $panel->titulo }}
                @if (filled($panel->version_panel))
                    <span
                        style="display:inline-block;margin-left:0.4rem;font-size:0.75em;font-weight:500;color:#6c757d;vertical-align:middle;">versión {{ $panel->version_panel }}</span>
                @endif
            </h1>
            @if (filled($panel->descripcion))
                <p class="cen-publico__empty" style="margin:0;">{{ $panel->descripcion }}</p>
            @endif
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;flex-shrink:0;">
            <button type="button" class="btn-login" id="cen-panel-fullscreen-btn"
                style="display:inline-block;padding:0.5rem 1rem;cursor:pointer;background:transparent;color:inherit;font:inherit;">
                Pantalla completa
            </button>
            @if ($panel->isHtmlBundle() && filled($panel->csv_path))
                <a class="btn-login" href="{{ $panel->csvDownloadUrl() }}"
                    style="display:inline-block;text-decoration:none;padding:0.5rem 1rem;">Descargar CSV</a>
            @endif
            @if (empty($isPreview) && !empty($panelInconsistencyMailto))
                <a class="btn-login" href="{{ $panelInconsistencyMailto }}"
                    style="display:inline-block;text-decoration:none;padding:0.5rem 1rem;">Reportar inconsistencia</a>
            @endif
        </div>
    </div>

    @if ($panel->isEmbedExterno())
        {{-- SDD-290: iframe hacia origen externo allowlist (no es el HTML curado de Centinela). --}}
        <iframe id="cen-panel-frame" class="cen-publico-panel-frame" title="{{ $panel->titulo }}"
            src="{{ $panel->frameSrc() }}"
            sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-downloads" allow="fullscreen"
            allowfullscreen referrerpolicy="no-referrer" loading="lazy"
            style="display:block;width:100%;min-height:70vh;border:0;background:#fff;"></iframe>
    @else
        {{-- SDD-283: iframe seamless (allow-scripts, sin allow-same-origin) + fullscreen desde chrome padre. --}}
        <iframe id="cen-panel-frame" class="cen-publico-panel-frame" title="{{ $panel->titulo }}"
            src="{{ $panel->frameSrc() }}" sandbox="allow-scripts" allow="fullscreen" allowfullscreen
            referrerpolicy="no-referrer" loading="lazy"
            style="display:block;width:100%;min-height:70vh;border:0;background:#fff;"></iframe>
    @endif

    <script>
        (function() {
            var frame = document.getElementById('cen-panel-frame');
            var btn = document.getElementById('cen-panel-fullscreen-btn');
            if (!frame || !btn) {
                return;
            }

            function isFullscreen() {
                return document.fullscreenElement === frame ||
                    document.webkitFullscreenElement === frame;
            }

            function syncLabel() {
                btn.textContent = isFullscreen() ? 'Salir de pantalla completa' : 'Pantalla completa';
            }

            btn.addEventListener('click', function() {
                if (isFullscreen()) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    }
                    return;
                }

                if (frame.requestFullscreen) {
                    frame.requestFullscreen();
                } else if (frame.webkitRequestFullscreen) {
                    frame.webkitRequestFullscreen();
                }
            });

            document.addEventListener('fullscreenchange', syncLabel);
            document.addEventListener('webkitfullscreenchange', syncLabel);
        })();
    </script>
@endsection
