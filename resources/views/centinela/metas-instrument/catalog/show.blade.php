@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
    @include('partials.metas.metas-print-styles')
@stop

@section('page_title', 'Indicador')

@section('page_header')
    <x-metas.page-header icon="voyager-eye">
        <code>{{ $indicator->clave }}</code> {{ $indicator->nombre }}
    </x-metas.page-header>
@stop

@section('content')
    <div class="page-content compass container-fluid metas-instrument-ui">
        @include('voyager::alerts')

        <x-metas.action-bar class="no-print">
            <a href="{{ route('metas.instrument.index', $indicatorType) }}" class="btn btn-success btn-sm">
                <i class="voyager-angle-left"></i> Volver al catálogo de indicadores
            </a>
            @if ($canManage)
                <a href="{{ route('metas.instrument.indicators.edit', [$indicatorType, $indicator]) }}"
                    class="btn btn-primary btn-sm">
                    <i class="voyager-edit"></i> Editar
                </a>
                @if ($canDelete)
                    <form method="POST"
                        action="{{ route('metas.instrument.indicators.destroy', [$indicatorType, $indicator]) }}"
                        class="metas-inline-form" onsubmit="return confirm('¿Seguro que deseas eliminar este indicador?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                            <i class="voyager-trash"></i> Eliminar
                        </button>
                    </form>
                @else
                    <button type="button" class="btn btn-danger btn-sm" disabled title="{{ $deleteBlockedReason }}">
                        <i class="voyager-trash"></i> Eliminar
                    </button>
                @endif
            @endif
        </x-metas.action-bar>

        @if (!empty($formulaIssue))
            <div class="metas-section-block metas-formula-issue-banner">
                <x-metas.formula-issue-alert variant="banner" :message="$formulaIssue['message']" />
                @if ($canManage)
                    <p class="metas-muted-text metas-section-spacer--compact">
                        <a href="{{ route('metas.instrument.indicators.edit', [$indicatorType, $indicator]) }}">
                            Editar indicador y definir fórmula
                        </a>
                    </p>
                @endif
            </div>
        @endif

        @if (!empty($incompletePeriodSummary))
            <div class="metas-section-block metas-incomplete-period-banner">
                <x-metas.incomplete-period-alert variant="banner" :center-count="$incompletePeriodSummary['center_count']" :message="$incompletePeriodSummary['message']" />
                @if ($canViewDesempeno)
                    <p class="metas-muted-text metas-section-spacer--compact">
                        <a href="{{ route('metas.instrument.indicador.carga', [$indicatorType, $indicator]) }}">
                            Ir a carga de datos para corregir
                        </a>
                    </p>
                @endif
            </div>
        @endif

        <x-indicators.detail-tabs :indicator-module="'metas'" :indicator-id="$indicator->id" informacion-tab-label="Ficha del indicador"
            informacion-tab-title="Ficha del indicador"
            informacion-tab-description="Definición del indicador, meta nacional, ámbitos y campos de datos para el año activo en su Servicio de Salud."
            aplicables-convenidos-tab-label="Establecimientos" aplicables-convenidos-tab-title="Establecimientos"
            aplicables-convenidos-tab-description="Establecimientos donde el indicador aplica o está en convenio para el año activo en su Servicio de Salud, incluyendo adosados configurados."
            desempeno-tab-label="Desempeño" bitacora-tab-title="Bitácora"
            bitacora-tab-description="Historial de cambios, evaluaciones y notas manuales asociadas a este indicador.">
            <x-slot:informacionActions>
                <span @class([
                    'label metas-estado-badge',
                    'label-success' => $indicator->estado,
                    'label-default' => !$indicator->estado,
                ])>
                    {{ $indicator->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </x-slot:informacionActions>
            @livewire(
                'metas-instrument.indicator-catalog-show-component',
                [
                    'indicatorType' => $indicatorType,
                    'indicator' => $indicator,
                ],
                key('metas-indicator-ficha-' . $indicator->id)
            )

            @if ($canViewDesempeno)
                <x-slot:aplicablesConvenidos>
                    @livewire(
                        'metas-instrument.indicator-establishments-component',
                        [
                            'indicatorType' => $indicatorType,
                            'indicator' => $indicator,
                        ],
                        key('metas-indicator-establishments-' . $indicator->id)
                    )
                </x-slot:aplicablesConvenidos>
                <x-slot:desempeno>
                    @if (!empty($calculationErrorSummary))
                        <div class="metas-section-block metas-calculation-error-banner">
                            <x-metas.calculation-error-alert variant="banner" :center-count="$calculationErrorSummary['center_count']" :message="$calculationErrorSummary['message']" />
                        </div>
                    @endif
                    @livewire(
                        'metas-instrument.indicator-desempeno-component',
                        [
                            'indicatorType' => $indicatorType,
                            'indicator' => $indicator,
                        ],
                        key('metas-indicator-desempeno-' . $indicator->id)
                    )
                </x-slot:desempeno>
            @endif
        </x-indicators.detail-tabs>
    </div>

    @include('partials.metas.indicator-data-modal')
@endsection

@push('javascript')
    @include('partials.metas.metas-copy-image-scripts')
    @include('livewire.metas-instrument.partials.indicator-data-modal-scripts', [
        'attachDownloadGuardsOnLoad' => true,
    ])
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var hash = window.location.hash;
            if (!hash) {
                return;
            }

            var tabLink = document.querySelector('.nav-tabs a[href="' + hash + '"]');
            if (tabLink && typeof jQuery !== 'undefined') {
                jQuery(tabLink).tab('show');
            }
        });
    </script>
@endpush
