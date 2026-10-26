@php
    use App\Models\PublicInfoPanel;

    $panel = $record instanceof PublicInfoPanel ? $record : null;
    $src = $panel?->frameSrc();
    $isEmbed = $panel?->isEmbedExterno() ?? false;
@endphp

<div class="fi-public-info-panel-preview">
    @if (!filled($src))
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Aún no hay HTML ni embed para previsualizar. Carga los archivos o la URL, guarda, y vuelve a esta vista.
        </p>
    @else
        <div class="mb-3 flex justify-end">
            <x-filament::button tag="a" :href="$src" target="_blank" rel="noopener noreferrer" color="gray"
                size="sm" icon="heroicon-o-arrow-top-right-on-square">
                Abrir en pestaña nueva
            </x-filament::button>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700">
            @if ($isEmbed)
                <iframe class="fi-public-info-panel-preview-frame" title="Vista previa: {{ $panel->titulo }}"
                    src="{{ $src }}"
                    sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-downloads"
                    allow="fullscreen" allowfullscreen referrerpolicy="no-referrer" loading="lazy"
                    style="display:block;width:100%;min-height:70vh;border:0;background:#fff;"></iframe>
            @else
                <iframe class="fi-public-info-panel-preview-frame" title="Vista previa: {{ $panel->titulo }}"
                    src="{{ $src }}" sandbox="allow-scripts" allow="fullscreen" allowfullscreen
                    referrerpolicy="no-referrer" loading="lazy"
                    style="display:block;width:100%;min-height:70vh;border:0;background:#fff;"></iframe>
            @endif
        </div>
    @endif
</div>
