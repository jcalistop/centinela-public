@php
    /** @var \App\Models\PublicInfoPanel|null $panel */
    $src = $panel?->frameSrc();
    $isEmbed = $panel?->isEmbedExterno() ?? false;
@endphp

<div class="public-info-panel-preview">
    @if (!filled($src))
        <p class="text-muted">
            Aún no hay HTML ni embed para previsualizar. Carga los archivos o la URL, guarda, y vuelve a esta vista.
        </p>
    @else
        <p class="text-right" style="margin-bottom: 8px;">
            <a href="{{ $src }}" target="_blank" rel="noopener noreferrer" class="btn btn-default btn-sm">
                Abrir en pestaña nueva
            </a>
        </p>
        <div style="border: 1px solid #e4eaec; border-radius: 4px; overflow: hidden; background: #fff;">
            @if ($isEmbed)
                <iframe class="public-info-panel-preview-frame" title="Vista previa: {{ $panel->titulo }}"
                    src="{{ $src }}"
                    sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-downloads"
                    allow="fullscreen" allowfullscreen referrerpolicy="no-referrer" loading="lazy"
                    style="display:block;width:100%;min-height:70vh;border:0;background:#fff;"></iframe>
            @else
                <iframe class="public-info-panel-preview-frame" title="Vista previa: {{ $panel->titulo }}"
                    src="{{ $src }}" sandbox="allow-scripts" allow="fullscreen" allowfullscreen
                    referrerpolicy="no-referrer" loading="lazy"
                    style="display:block;width:100%;min-height:70vh;border:0;background:#fff;"></iframe>
            @endif
        </div>
    @endif
</div>
