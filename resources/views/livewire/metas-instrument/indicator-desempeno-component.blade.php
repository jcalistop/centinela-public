<div class="metas-tab-panel">
    <div class="clearfix no-print metas-desempeno-print-toolbar">
        @if ($canShowCargaButton)
            <a href="{{ route('metas.instrument.indicador.carga', ['indicatorType' => $indicatorTypeRoute, 'indicator' => $indicatorId]) }}"
                class="btn btn-primary btn-sm pull-left" title="Cargar datos">
                <i class="voyager-upload"></i> Cargar datos
            </a>
        @endif
        <div class="pull-right">
            <x-metas.print-button class="metas-carga-btn-spaced" />
            <x-metas.copy-image-button target="#metas-capture-desempeno" />
        </div>
    </div>

    <div class="no-print">
        <x-metas.semaforo-legend context="indicator" :umbral-superior="$umbralSuperior" :umbral-inferior="$umbralInferior" />
    </div>

    <div id="metas-capture-desempeno" class="metas-capture-root">
        <div class="metas-print-header print-only">
            <h2>Desempeño — {{ $indicatorYearAno }}</h2>
        </div>

        @include('livewire.metas-instrument.partials.indicator-desempeno-table', [
            'desempenoData' => $desempenoData,
            'desempenoUsesComplexPrimaryComponents' => $usesComplexPrimaryComponents,
            'desempenoUmbralSuperior' => $umbralSuperior,
            'desempenoUmbralInferior' => $umbralInferior,
            'desempenoPromedioCumplimiento' => $promedioCumplimiento,
            'indicatorId' => $indicatorId,
        ])
    </div>

    @include('livewire.metas-instrument.partials.complex-formula-variables-modal')
</div>
