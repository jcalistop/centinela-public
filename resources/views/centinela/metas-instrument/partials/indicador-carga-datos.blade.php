@if (!empty($effectiveFieldTypeRows))
    <x-metas.field-types-readonly :rows="$effectiveFieldTypeRows" variant="flat" />
@endif

@if ($indicator->formula?->description)
    <section class="metas-carga-hub-formula">
        <h4 class="metas-ficha-section__heading">
            <i class="voyager-code"></i> Fórmula de cálculo
        </h4>
        <p class="metas-carga-hub-formula__text text-muted">{{ $indicator->formula->description }}</p>
    </section>
@endif

@include('centinela.metas-instrument.partials.indicador-carga-hub')
