@php
    $hasPanels = ($total ?? 0) > 0;
@endphp

<div class="space-y-3" x-data="{
    format: 'markdown',
    copied: false,
    markdown: {{ \Illuminate\Support\Js::from($markdown ?? '') }},
    json: {{ \Illuminate\Support\Js::from($json ?? '') }},
    get text() {
        return this.format === 'markdown' ? this.markdown : this.json;
    },
    async copy() {
        if (!this.text) {
            return;
        }

        try {
            await navigator.clipboard.writeText(this.text);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        } catch (e) {
            this.copied = false;
        }
    }
}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex gap-2">
            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-table-cells"
                x-on:click="format = 'markdown'">
                Markdown
            </x-filament::button>
            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-code-bracket"
                x-on:click="format = 'json'">
                JSON
            </x-filament::button>
        </div>

        <x-filament::button type="button" size="sm" icon="heroicon-o-clipboard-document" x-on:click="copy()"
            x-bind:disabled="!text">
            <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
        </x-filament::button>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        @if ($hasPanels)
            El texto queda listo para pegar en el agente externo. No se descarga ningún archivo.
        @else
            Sin paneles registrados en el catálogo.
        @endif
    </p>

    <pre class="max-h-[60vh] overflow-auto whitespace-pre rounded-lg border border-gray-200 bg-white p-4 text-xs text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white"
        x-text="text"></pre>
</div>
