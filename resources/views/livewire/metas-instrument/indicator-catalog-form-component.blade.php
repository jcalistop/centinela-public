<div>
    <x-metas.bordered-panel icon="voyager-info-circled">
        <x-slot:heading>Ficha del indicador</x-slot:heading>
        <x-slot:headingActions>
            @include('livewire.metas-instrument.partials.indicator-catalog-estado-toggle')
        </x-slot:headingActions>
        @include('livewire.metas-instrument.partials.indicator-catalog-form-fields')
    </x-metas.bordered-panel>

    <x-metas.bordered-panel class="metas-form-panel-spacer">
        <x-slot:heading>Maqueta de datos y fórmula</x-slot:heading>
        @include('livewire.metas-instrument.partials.indicator-maqueta-section')
    </x-metas.bordered-panel>

    <div class="metas-form-actions text-right">
        <a href="{{ route('metas.instrument.index', $indicatorType) }}" class="btn btn-default">
            Cancelar
        </a>
        <button type="button" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save" x-data
            x-on:click="
                const builder = document.getElementById('indicator-catalog-formula-builder');
                let payload = '[]';
                if (builder && typeof Alpine !== 'undefined') {
                    const builderState = Alpine.$data(builder);
                    if (builderState) {
                        payload = JSON.stringify(builderState.tokens);
                    }
                }
                $wire.set('formulaJson', payload).then(() => $wire.save());
            ">
            <span wire:loading wire:target="save">
                <i class="voyager-loader"></i>
            </span>
            {{ $isEditing ? 'Guardar cambios' : 'Crear indicador' }}
        </button>
    </div>

    @unless (app()->runningUnitTests())
        @include('livewire.metas-instrument.partials.indicator-catalog-formula-builder-script')
    @endunless
</div>
