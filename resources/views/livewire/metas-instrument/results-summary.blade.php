<div>
    @php
        $semaforo = \App\Support\Metas\MetSemaforoThresholds::forIndicatorType(
            (int) $indicatorType->id,
            $indicatorType->route,
        );
    @endphp

    @if ($canConfigureEstablishments)
        <div class="metas-section-block" style="margin-bottom: 1rem;">
            <a href="{{ $configureEstablishmentsUrl }}" class="btn btn-primary btn-sm">
                <i class="voyager-company"></i> Configurar establecimientos
            </a>
        </div>
    @endif

    <x-metas.semaforo-legend context="global" :umbral-superior="$semaforo->greenMinPercent" :umbral-amarillo="$semaforo->yellowMinPercent" />

    @if ($configIssuesByCenterId->isNotEmpty())
        @php
            $affectedCenterNames = $centers
                ->filter(fn($c) => $configIssuesByCenterId->has($c->id))
                ->pluck('nombre_corto')
                ->values();
            $displayNames = $affectedCenterNames->take(5);
            $remainingCount = max(0, $affectedCenterNames->count() - $displayNames->count());
            $bannerMessage =
                $configIssuesByCenterId->count() .
                ' establecimiento(s) con configuración incompleta: ' .
                $displayNames->implode(', ');
            if ($remainingCount > 0) {
                $bannerMessage .= ' y ' . $remainingCount . ' más';
            }
        @endphp
        <div class="metas-section-block metas-establishments-config-banner">
            <x-metas.establishment-config-alert variant="banner" :message="$bannerMessage" />
        </div>
    @endif

    @if ($centers->isEmpty())
        <div class="alert alert-info">
            <i class="voyager-bar-chart"></i>
            No hay establecimientos con resultados para el año <strong>{{ session('indicator_year')->ano }}</strong>.
            Los resultados se actualizan en segundo plano al guardar carga o configuración del establecimiento
            (etapa Autoevaluación), o puede recalcularse todo el instrumento desde
            <strong>Configuración del instrumento</strong>.
        </div>
    @elseif ($usesInternalUnitMeasurement)
        <div class="metas-results-centers-by-unit">
            @foreach ($centers as $center)
                @php
                    $globals = $globalResultsByEtapa[$center->id] ?? [];
                    $latestGlobal = $latestGlobalByCenter[$center->id] ?? null;
                    $headerBorderColor = $semaforo->borderColor($latestGlobal);
                    $centerUnits = $unitsByCenter->get($center->id, collect());
                @endphp
                <section class="metas-results-center-section" wire:key="center-section-{{ $center->id }}">
                    <header class="metas-results-center-section__header"
                        style="border-left-color: {{ $headerBorderColor }}">
                        <div class="metas-results-center-section__title">
                            <i class="voyager-hospital metas-results-center-section__icon"></i>
                            <div>
                                <div class="metas-results-center-section__name" title="{{ $center->nombre_corto }}">
                                    {{ $center->nombre_corto }}
                                </div>
                                @if ($configIssuesByCenterId->has($center->id))
                                    <div class="metas-center-config-issue" style="margin-top: 0.4rem;">
                                        @if ($canConfigureEstablishments)
                                            <a
                                                href="{{ route('metas.instrument.establishments.indicators', [
                                                    'indicatorType' => $indicatorType,
                                                    'center' => $center->id,
                                                ]) }}">
                                                <x-metas.establishment-config-alert :message="$configIssuesByCenterId[$center->id]['message']" />
                                            </a>
                                        @else
                                            <x-metas.establishment-config-alert :message="$configIssuesByCenterId[$center->id]['message']" />
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="metas-results-center-section__stages">
                            @php
                                $agreement = $agreementsByCenterId->get($center->id);
                            @endphp
                            <div class="card-stage-row card-agreement">
                                <span class="card-stage-label">Estado del convenio</span>
                                <span class="card-stage-value">
                                    @if ($agreement?->isEnviado())
                                        <span class="label label-success">Enviado</span>
                                    @else
                                        <span class="label label-default">Borrador</span>
                                    @endif
                                </span>
                            </div>
                            @foreach ($stageLabels as $etapaKey => $stageLabel)
                                @php
                                    $global = $globals[$etapaKey] ?? null;
                                    $badgeClass = $semaforo->labelClass($global);
                                @endphp
                                <div class="card-stage-row"
                                    wire:key="center-header-{{ $center->id }}-{{ $etapaKey }}">
                                    <span class="card-stage-label">{{ $stageLabel }}</span>
                                    <span class="card-stage-value">
                                        @if ($global !== null)
                                            <span class="label label-{{ $badgeClass }}">
                                                {{ number_format($global, 2) }}%
                                            </span>
                                        @else
                                            <span class="text-muted metas-result-card-empty">Sin resultado</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </header>

                    @if ($centerUnits->isEmpty())
                        <div class="alert alert-info metas-results-center-section__empty-units">
                            <i class="voyager-info-circled"></i>
                            Sin unidades activas registradas para este establecimiento.
                            Gestione las unidades desde la pestaña <strong>Participantes</strong>.
                        </div>
                    @else
                        <div class="metas-instrument-results-grid metas-instrument-results-grid--units">
                            @foreach ($centerUnits as $unit)
                                @php
                                    $unitGlobals = $globalResultsByUnitAndEtapa[$center->id][$unit->id] ?? [];
                                    $latestUnitGlobal = null;
                                    foreach (
                                        [
                                            \App\Enums\IndicatorType\EtapaVigente::Final->value,
                                            \App\Enums\IndicatorType\EtapaVigente::Preliminar->value,
                                            \App\Enums\IndicatorType\EtapaVigente::Autoevaluacion->value,
                                        ]
                                        as $etapaPriority
                                    ) {
                                        if (
                                            isset($unitGlobals[$etapaPriority]) &&
                                            $unitGlobals[$etapaPriority] !== null
                                        ) {
                                            $latestUnitGlobal = $unitGlobals[$etapaPriority];
                                            break;
                                        }
                                    }
                                    $unitBorderColor = $semaforo->borderColor($latestUnitGlobal);
                                    $detailUrl = route('metas.instrument.establishment.results', [
                                        'indicatorType' => $indicatorType,
                                        'center' => $center->id,
                                        'unidad' => $unit->id,
                                    ]);
                                @endphp
                                <a href="{{ $detailUrl }}"
                                    class="metas-instrument-result-card metas-instrument-result-card--unit"
                                    style="border-left-color: {{ $unitBorderColor }}"
                                    wire:key="unit-card-{{ $center->id }}-{{ $unit->id }}">
                                    <i class="voyager-layers card-icon"></i>
                                    <div class="card-name" title="{{ $unit->nombre ?: $unit->codigo }}">
                                        {{ $unit->codigo }}
                                    </div>
                                    @if ($unit->nombre)
                                        <div class="card-code">{{ $unit->nombre }}</div>
                                    @endif

                                    @foreach ($stageLabels as $etapaKey => $stageLabel)
                                        @php
                                            $global = $unitGlobals[$etapaKey] ?? null;
                                            $badgeClass = $semaforo->labelClass($global);
                                        @endphp
                                        <div class="card-stage-row"
                                            wire:key="unit-card-{{ $center->id }}-{{ $unit->id }}-{{ $etapaKey }}">
                                            <span class="card-stage-label">{{ $stageLabel }}</span>
                                            <span class="card-stage-value">
                                                @if ($global !== null)
                                                    <span class="label label-{{ $badgeClass }}">
                                                        {{ number_format($global, 2) }}%
                                                    </span>
                                                @else
                                                    <span class="text-muted metas-result-card-empty">Sin
                                                        resultado</span>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @else
        <div class="metas-instrument-results-grid">
            @foreach ($centers as $center)
                @php
                    $globals = $globalResultsByEtapa[$center->id] ?? [];
                    $latestGlobal = $latestGlobalByCenter[$center->id] ?? null;
                    $borderColor = $semaforo->borderColor($latestGlobal);
                    $detailUrl = route('metas.instrument.establishment.results', [
                        'indicatorType' => $indicatorType,
                        'center' => $center->id,
                    ]);
                @endphp
                <a href="{{ $detailUrl }}" class="metas-instrument-result-card"
                    style="border-left-color: {{ $borderColor }}" wire:key="card-{{ $center->id }}">
                    <i class="voyager-hospital card-icon"></i>
                    <div class="card-name" title="{{ $center->nombre_corto }}">
                        {{ $center->nombre_corto }}
                    </div>
                    @if ($configIssuesByCenterId->has($center->id))
                        <div class="metas-center-config-issue" style="margin-top: 0.4rem;">
                            <x-metas.establishment-config-alert :message="$configIssuesByCenterId[$center->id]['message']" />
                        </div>
                    @endif

                    @php
                        $agreementCard = $agreementsByCenterId->get($center->id);
                    @endphp
                    <div class="card-stage-row card-agreement">
                        <span class="card-stage-label">Estado del convenio</span>
                        <span class="card-stage-value">
                            @if ($agreementCard?->isEnviado())
                                <span class="label label-success">Enviado</span>
                            @else
                                <span class="label label-default">Borrador</span>
                            @endif
                        </span>
                    </div>

                    @foreach ($stageLabels as $etapaKey => $stageLabel)
                        @php
                            $global = $globals[$etapaKey] ?? null;
                            $badgeClass = $semaforo->labelClass($global);
                        @endphp
                        <div class="card-stage-row" wire:key="card-{{ $center->id }}-{{ $etapaKey }}">
                            <span class="card-stage-label">{{ $stageLabel }}</span>
                            <span class="card-stage-value">
                                @if ($global !== null)
                                    <span class="label label-{{ $badgeClass }}">
                                        {{ number_format($global, 2) }}%
                                    </span>
                                @else
                                    <span class="text-muted metas-result-card-empty">Sin resultado</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </a>
            @endforeach
        </div>
    @endif
</div>
