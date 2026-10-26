@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\IndicatorAmbito> $indicatorAmbitoOptions */
    /** @var list<int> $selectedIndicatorAmbitoIds */
    $indicatorAmbitoOptions = $indicatorAmbitoOptions ?? collect();
    $selectedIndicatorAmbitoIds = $selectedIndicatorAmbitoIds ?? [];
    $pickerMode = $pickerMode ?? 'form';
    $wireModelProperty = $wireModelProperty ?? 'selectedAmbitoIds';
@endphp

<div class="@if ($pickerMode === 'form') form-group col-md-12 @endif indicator-ambitos-field">
    <label class="control-label" for="indicator_ambito_ids">Ámbitos</label>
    <p class="help-block text-muted indicator-ambitos-field__intro">
        Clasificación opcional para agrupar indicadores en catálogo y dashboard. Distinto del nivel de aplicación
        operativa.
    </p>

    @if ($pickerMode === 'livewire')
        <div class="indicator-ambitos-picker" x-data="{ filter: '' }">
            <input type="search" class="form-control indicator-ambitos-picker__search" x-model="filter"
                placeholder="Buscar ámbito…" autocomplete="off" aria-label="Buscar ámbito">

            @if ($indicatorAmbitoOptions->isEmpty())
                <p class="text-muted indicator-ambitos-picker__empty">No hay ámbitos activos configurados.</p>
            @else
                <div class="indicator-ambitos-picker__list" role="group" aria-label="Selección de ámbitos">
                    @foreach ($indicatorAmbitoOptions as $ambito)
                        <label class="indicator-ambitos-picker__item"
                            data-search="{{ \Illuminate\Support\Str::lower($ambito->nombre) }}"
                            x-show="!filter || $el.dataset.search.includes(filter.toLowerCase())"
                            wire:key="ambito-picker-{{ $ambito->id }}">
                            <input type="checkbox" wire:model="{{ $wireModelProperty }}" value="{{ $ambito->id }}">
                            <span>{{ $ambito->nombre }}</span>
                        </label>
                    @endforeach
                </div>
            @endif

            <p class="help-block indicator-ambitos-field__hint">Marque uno o más ámbitos. Sin selección = «Sin ámbito».
            </p>
        </div>
    @else
        <select name="indicator_ambito_ids[]" id="indicator_ambito_ids"
            class="form-control select2 indicator-ambitos-select" multiple
            data-placeholder="Buscar y seleccionar ámbitos…">
            @foreach ($indicatorAmbitoOptions as $ambito)
                <option value="{{ $ambito->id }}" @selected(in_array($ambito->id, $selectedIndicatorAmbitoIds, true))>
                    {{ $ambito->nombre }}
                </option>
            @endforeach
        </select>
        <p class="help-block indicator-ambitos-field__hint">Busque y elija uno o más ámbitos. Vacío = «Sin ámbito».</p>

        @once
            @push('javascript')
                <script>
                    (function initIndicatorAmbitosSelect2() {
                        function boot() {
                            if (!window.jQuery || !window.jQuery.fn.select2) {
                                return;
                            }

                            var $select = window.jQuery('#indicator_ambito_ids.indicator-ambitos-select');

                            if (!$select.length) {
                                return;
                            }

                            if ($select.data('select2')) {
                                $select.select2('destroy');
                            }

                            $select.select2({
                                width: '100%',
                                placeholder: $select.data('placeholder') || 'Buscar y seleccionar ámbitos…',
                                allowClear: true,
                                closeOnSelect: false,
                            });
                        }

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', boot);
                        } else {
                            boot();
                        }
                    })
                    ();
                </script>
            @endpush
        @endonce
    @endif
</div>
