<div>
    <div class="metas-results-detail-toolbar no-print">
        <div class="metas-results-detail-toolbar__filter">
            <span class="metas-results-detail-toggle-label">Filtrar indicadores en convenio:</span>
            <label class="switch-toggle metas-results-detail-solo-comprometidos-toggle" style="margin-bottom:0;">
                <input type="checkbox" wire:model.live="soloComprometidos">
                <span class="switch-slider"></span>
            </label>
            <span class="switch-label">{{ $soloComprometidos ? 'Sí' : 'No' }}</span>
        </div>
        <div class="metas-results-detail-toolbar__actions">
            @if ($canEditEstablishmentIndicators ?? false)
                <a href="{{ route('metas.instrument.establishments.indicators', [
                    'indicatorType' => $indicatorTypeRoute,
                    'center' => $center->id,
                ]) }}"
                    class="btn btn-primary btn-sm metas-carga-btn-spaced">
                    <i class="voyager-params"></i> Configurar convenio
                </a>
            @endif
            <x-metas.print-button class="metas-carga-btn-spaced" />
            <x-metas.copy-image-button target="#metas-capture-autoevaluacion" class="metas-carga-btn-spaced" />
        </div>
    </div>

    @php
        $semaforo = \App\Support\Metas\MetSemaforoThresholds::forIndicatorType(
            (int) $indicatorTypeId,
            $indicatorTypeRoute,
        );
    @endphp

    @if ($showAveragedUnitsBanner ?? false)
        <div class="alert alert-warning metas-results-averaged-units-banner no-print">
            <i class="voyager-info-circled"></i>
            Los valores mostrados son un <strong>promedio de todas las unidades</strong> de medición de este
            establecimiento. Para ver el desglose por unidad, vuelva a la pestaña
            <strong>Resultados</strong> del instrumento y seleccione la unidad correspondiente.
        </div>
    @endif

    @if (!empty($configIssue))
        <div class="metas-section-block metas-establishments-config-banner no-print">
            @if ($canEditEstablishmentIndicators ?? false)
                <a
                    href="{{ route('metas.instrument.establishments.indicators', [
                        'indicatorType' => $indicatorTypeRoute,
                        'center' => $center->id,
                    ]) }}">
                    <x-metas.establishment-config-alert variant="banner" :message="$configIssue['message'] ?? null" />
                </a>
            @else
                <x-metas.establishment-config-alert variant="banner" :message="$configIssue['message'] ?? null" />
            @endif
        </div>
    @endif

    <div class="no-print">
        <x-metas.semaforo-legend context="mixed" />
    </div>

    <div id="metas-capture-autoevaluacion" class="metas-capture-root">
        <div class="metas-print-header print-only">
            <h2>{{ $indicatorType->nombre_indicador }} — {{ $center->nombre_corto }}</h2>
            <p>
                Año {{ $indicatorYearAno }}
                · Etapa Autoevaluación
                @if ($measurementUnit)
                    · Unidad <code>{{ $measurementUnit->codigo }}</code>
                    @if ($measurementUnit->nombre)
                        ({{ $measurementUnit->nombre }})
                    @endif
                @endif
            </p>
        </div>

        @if ($byIndicator->isEmpty())
            <p class="text-muted text-center metas-results-detail-empty">
                @if ($soloComprometidos)
                    <em>No hay indicadores en convenio para este establecimiento.</em>
                @else
                    <em>No hay resultados de indicadores para este establecimiento.</em>
                @endif
            </p>
        @else
            <div class="table-responsive">
                <table
                    class="table table-bordered table-condensed table-results metas-instrument-table metas-results-table">
                    <thead>
                        <tr>
                            <th class="metas-results-col--codigo metas-results-col--nowrap">Código</th>
                            <th class="metas-results-col--nombre text-left">Nombre indicador</th>
                            <th class="text-center metas-results-col--comprometido">En convenio</th>
                            <th class="text-center metas-results-col--meta-efectiva">Meta efectiva</th>
                            <th class="text-center metas-results-col--datos-al">Datos al</th>
                            <th class="text-center metas-results-col--formula"
                                title="En fórmulas simples: numerador ÷ denominador del período. Si no hay un par único num/den: Fórmula compleja.">
                                Fórmula de cálculo
                            </th>
                            <th class="text-center metas-results-col--resultado">Resultado</th>
                            <th class="text-center metas-results-col--cumplimiento">Cumplimiento</th>
                            <th class="text-center metas-results-col--ponderacion">Ponderación</th>
                            <th class="text-center metas-results-col--cumpl-pond">Cumplimiento ponderado</th>
                            <th class="text-center metas-results-col--ver-datos no-print">Datos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byIndicator as $indicatorId => $periodResults)
                            @php
                                $indicator = $periodResults->first()->indicator;
                                $config = $configurations->get($indicatorId);
                                $rowspan = $periodResults->count();
                                $ponderacion = (float) ($config?->ponderacion ?? 0);
                                $comprometido = $config?->seleccionado ?? false;
                                $metaNacional = (float) ($indicator?->meta_valor ?? 0);
                                $metaEfectiva =
                                    $config?->meta_efectiva !== null ? (float) $config->meta_efectiva : $metaNacional;
                                $metaOp = $indicator?->meta_operador?->value ?? '>=';
                                $metaEfectivaDiffersFromNacional =
                                    $indicator?->meta_valor !== null &&
                                    round($metaEfectiva, 2) !== round($metaNacional, 2);
                                $metaNacionalTooltip = $metaEfectivaDiffersFromNacional
                                    ? \App\Support\Metas\MetIndicatorMetaPresenter::plainLabel(
                                        $metaNacional,
                                        $metaOp,
                                        $indicator?->unidad_medida,
                                    )
                                    : null;
                                $decimales = $indicator?->decimales ?? 2;
                                $decimalesModo = $indicator?->decimales_modo ?? 'redondear';
                                $usesComplexPrimaryComponents = $indicator
                                    ? \App\Support\Metas\MetIndicatorPrimaryComponentPresenter::usesComplexPrimaryComponents(
                                        $indicator->dataFields,
                                    )
                                    : false;
                            @endphp
                            @foreach ($periodResults as $ir)
                                <tr>
                                    @if ($loop->first)
                                        <td rowspan="{{ $rowspan }}" class="metas-results-col--nowrap">
                                            <code class="metas-results-code">{{ $indicator?->clave }}</code>
                                        </td>
                                        <td rowspan="{{ $rowspan }}" class="metas-results-col--nombre text-left">
                                            {{ $indicator?->nombre }}
                                        </td>
                                        <td rowspan="{{ $rowspan }}" class="text-center">
                                            @if ($comprometido)
                                                <span class="label label-success">Sí</span>
                                            @else
                                                <span class="label label-default">No</span>
                                            @endif
                                        </td>
                                        <td rowspan="{{ $rowspan }}" class="text-center">
                                            @if ($metaEfectivaDiffersFromNacional)
                                                <span data-metas-tooltip
                                                    title="Meta nacional (referencia): {{ $metaNacionalTooltip }}">
                                                    @include(
                                                        'livewire.metas-instrument.partials.indicator-meta-value',
                                                        [
                                                            'value' => $metaEfectiva,
                                                            'metaOperador' => $metaOp,
                                                            'unidadMedida' => $indicator?->unidad_medida,
                                                        ]
                                                    )
                                                    {{-- Icono Voyager no rasteriza con skipFonts (SDD-180); omitir en PNG. --}}
                                                    <i class="voyager-info-circled text-muted no-capture"
                                                        aria-hidden="true"></i>
                                                </span>
                                            @else
                                                @include(
                                                    'livewire.metas-instrument.partials.indicator-meta-value',
                                                    [
                                                        'value' => $metaEfectiva,
                                                        'metaOperador' => $metaOp,
                                                        'unidadMedida' => $indicator?->unidad_medida,
                                                    ]
                                                )
                                            @endif
                                        </td>
                                    @endif
                                    @php
                                        $loadSnapshot = $dataLoadSnapshotsByIndicator->get($indicatorId, [
                                            'ultimo_mes_cargado' => null,
                                            'estado_carga' => \App\Enums\Metas\MetDataLoadStatus::SinDatos,
                                        ]);
                                        $estadoCarga = $loadSnapshot['estado_carga'];
                                        if (
                                            filled($ir->error_message) &&
                                            !\App\Models\MetResult::storedErrorMessageIsNonCalculationAlert(
                                                $ir->error_message,
                                            )
                                        ) {
                                            $estadoCarga = \App\Enums\Metas\MetDataLoadStatus::ErrorCalculo;
                                        }
                                    @endphp
                                    <x-metas.datos-al-badge :ultimo-mes-cargado="$loadSnapshot['ultimo_mes_cargado'] ?? null" :estado-carga="$estadoCarga" />
                                    @if ($ir->error_message)
                                        @include(
                                            'livewire.metas-instrument.partials.result-issue-from-stored-message',
                                            [
                                                'storedMessage' => $ir->error_message,
                                                'asSeparateCells' => true,
                                                'separateInputCells' => 1,
                                            ]
                                        )
                                        @if ($loop->first)
                                            <td rowspan="{{ $rowspan }}" class="text-center">
                                                {{ number_format($ponderacion, 2) }}%
                                            </td>
                                            <td rowspan="{{ $rowspan }}" class="text-center text-muted">
                                                —
                                            </td>
                                        @endif
                                    @else
                                        @php
                                            $resultado = (float) $ir->resultado;
                                            $cumplimiento =
                                                $metaEfectiva > 0
                                                    ? $calculationService->indicatorCompliancePercent(
                                                        $indicator,
                                                        $resultado,
                                                        $metaEfectiva,
                                                    )
                                                    : null;
                                            $cumplPonderado = $loop->first
                                                ? $weightedComplianceByIndicator[$indicatorId] ?? null
                                                : null;
                                            $cumplimientoLabelClass = \App\Support\Metas\MetSemaforoThresholds::labelClassForIndicatorCompliance(
                                                $cumplimiento,
                                                $indicator?->umbral_superior !== null
                                                    ? (float) $indicator->umbral_superior
                                                    : null,
                                                $indicator?->umbral_inferior !== null
                                                    ? (float) $indicator->umbral_inferior
                                                    : null,
                                            );
                                        @endphp
                                        @if ($usesComplexPrimaryComponents)
                                            @if ($loop->first)
                                                <td rowspan="{{ $rowspan }}"
                                                    class="text-center text-muted metas-results-complex-formula">
                                                    <button type="button"
                                                        class="btn btn-link btn-xs metas-complex-formula-variables-trigger no-print no-capture"
                                                        wire:click="openComplexFormulaVariablesModal({{ (int) $indicatorId }}, {{ (int) $centerId }}, @js($ir->periodo === 'ANUAL' ? $ir->ultimo_periodo ?? null : $ir->periodo), {{ $ir->periodo === 'ANUAL' ? 'true' : 'false' }})"
                                                        title="Ver variables de cálculo">
                                                        Fórmula compleja
                                                    </button>
                                                    <span class="print-only">Fórmula compleja</span>
                                                </td>
                                            @endif
                                        @else
                                            <td class="text-center">
                                                {{ \App\Support\Metas\MetIndicatorMetaPresenter::formatCompactNumber((float) $ir->numerador) .
                                                    ' / ' .
                                                    \App\Support\Metas\MetIndicatorMetaPresenter::formatCompactNumber((float) $ir->denominador) }}
                                            </td>
                                        @endif
                                        <td class="text-center">
                                            @include(
                                                'livewire.metas-instrument.partials.indicator-result-value',
                                                [
                                                    'value' => $resultado,
                                                    'decimales' => $decimales,
                                                    'unidadMedida' => $indicator?->unidad_medida,
                                                ]
                                            )
                                        </td>
                                        <td class="text-center">
                                            @if ($cumplimiento !== null)
                                                <span class="label label-{{ $cumplimientoLabelClass }}">
                                                    {{ number_format($cumplimiento, 2) }}%
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        @if ($loop->first)
                                            <td rowspan="{{ $rowspan }}" class="text-center">
                                                {{ number_format($ponderacion, 2) }}%
                                            </td>
                                            <td rowspan="{{ $rowspan }}" class="text-center">
                                                @if ($cumplPonderado !== null)
                                                    {{ number_format($cumplPonderado, 2) }}%
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    @endif
                                    @if ($loop->first)
                                        <td rowspan="{{ $rowspan }}" class="text-center no-print">
                                            <x-metas.indicator-data-actions :wire-click="'openDataModal(' . $indicatorId . ')'" />
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                        @if ($footerCumplimientoGlobal !== null)
                            @php
                                $anualCumplFooter = (float) $footerCumplimientoGlobal;
                                $footerBadgeClass = $semaforo->labelClass($anualCumplFooter);
                            @endphp
                            <tr class="metas-results-footer-row">
                                {{-- Pantalla/print y captura PNG: 9 cols antes del badge (Fórmula de cálculo incluida). --}}
                                <td colspan="9" class="text-right no-capture">
                                    <strong>Cumplimiento global</strong>
                                    <br>
                                    <small class="text-muted">{{ $semaforo->globalLegendFootnote() }}</small>
                                </td>
                                <td colspan="9" class="text-right capture-only">
                                    <strong>Cumplimiento global</strong>
                                    <br>
                                    <small class="text-muted">{{ $semaforo->globalLegendFootnote() }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="label label-{{ $footerBadgeClass }}">
                                        <strong>{{ number_format($anualCumplFooter, 2) }}%</strong>
                                    </span>
                                </td>
                                <td class="text-center no-print"></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="no-print">
                <x-metas.datos-al-legend />
            </div>
        @endif
    </div>{{-- /#metas-capture-autoevaluacion --}}

    @include('livewire.metas-instrument.partials.complex-formula-variables-modal')
</div>
