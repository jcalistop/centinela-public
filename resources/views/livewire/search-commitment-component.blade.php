<div wire:key="search-commitment-year-{{ $yearId ?? 'none' }}">
    @include('livewire.comges.partials.search-commitment-styles')

    <x-comges.semaforo-legend :useScoreScale="$useScoreScale" />

    <div class="comges-commitment-filters">
        <div class="comges-commitment-filters__row">
            <div class="comges-commitment-filters__field comges-commitment-filters__field--search">
                <label for="comges-commitment-search" class="comges-commitment-filters__label">Buscar</label>
                <input id="comges-commitment-search" type="text" class="form-control"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ $useScoreScale ? 'Buscar por clave o nombre de compromiso o indicador...' : 'Ingresa un texto para buscar...' }}"
                    aria-label="Buscar compromisos e indicadores">
            </div>
            <div class="comges-commitment-filters__field comges-commitment-filters__field--ambito">
                <label for="comges-commitment-ambito" class="comges-commitment-filters__label">Ámbito</label>
                <select id="comges-commitment-ambito" wire:model.live="ambitoFilter" class="form-control"
                    aria-label="Filtrar por ámbito">
                    <option value="">Todos los ámbitos</option>
                    <option value="none">Sin ámbito</option>
                    @foreach ($indicatorAmbitoOptions as $ambito)
                        <option value="{{ $ambito->id }}">{{ $ambito->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @foreach ($commitments as $commitment)
        @if ($useScoreScale)
            @include('livewire.comges.partials.commitment-panel-score', [
                'commitment' => $commitment,
                'isFiltering' => $isFiltering,
                'alertGroups' => $alertGroups,
            ])
        @else
            @include('livewire.comges.partials.commitment-panel-legacy', [
                'commitment' => $commitment,
            ])
        @endif
    @endforeach
</div>
