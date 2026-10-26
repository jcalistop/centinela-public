@php
    use App\Support\Dashboard\PublicInfoPanelAgentGuide;
    use Illuminate\Support\Str;

    $markdown = PublicInfoPanelAgentGuide::contents();
    $html = Str::markdown($markdown);
@endphp

<div class="space-y-3" x-data="{
    copied: false,
    text: {{ \Illuminate\Support\Js::from($markdown) }},
    async copy() {
        try {
            await navigator.clipboard.writeText(this.text);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        } catch (e) {
            this.copied = false;
        }
    }
}">
    <div class="flex justify-end gap-2">
        <x-filament::button tag="a" :href="route('filament.public-info-panels.agent-guide')" color="gray" size="sm"
            icon="heroicon-o-arrow-down-tray">
            Descargar .md
        </x-filament::button>
        <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-clipboard-document"
            x-on:click="copy()" x-bind:disabled="!text">
            <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
        </x-filament::button>
    </div>

    <div
        class="fi-prose max-h-[60vh] overflow-y-auto rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white [&_h1]:mb-2 [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:mb-2 [&_h2]:mt-4 [&_h2]:text-lg [&_h2]:font-semibold [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-5 [&_table]:my-3 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-gray-200 [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:border-gray-200 [&_th]:px-2 [&_th]:py-1 dark:[&_td]:border-white/10 dark:[&_th]:border-white/10 [&_code]:rounded [&_code]:bg-gray-100 [&_code]:px-1 dark:[&_code]:bg-white/10 [&_p]:my-2 [&_strong]:font-semibold">
        {!! $html !!}
    </div>
</div>
