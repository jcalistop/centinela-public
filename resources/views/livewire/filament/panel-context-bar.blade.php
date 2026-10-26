<div class="fi-panel-context-bar flex items-center gap-x-3 pe-2" wire:key="filament-panel-context-bar">
    <label class="sr-only" for="filament-indicator-year">Año indicador</label>
    <select id="filament-indicator-year" wire:model.live="yearId"
        class="fi-input block border-none bg-gray-50 px-2.5 py-1.5 text-gray-950 shadow-sm ring-1 ring-gray-950/10 outline-none transition duration-75 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:focus:ring-primary-500"
        @disabled($years->isEmpty())>
        @forelse ($years as $year)
            <option value="{{ $year->id }}">{{ $year->ano }}</option>
        @empty
            <option value="">Sin años</option>
        @endforelse
    </select>

    @if (filled($ssName))
        <span
            class="fi-ss-chip flex items-center justify-center gap-x-1 bg-primary-50 px-2.5 py-1.5 font-medium text-primary-700 ring-1 ring-inset ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30"
            title="Servicio de Salud">
            {{ $ssName }}
        </span>
    @endif
</div>
