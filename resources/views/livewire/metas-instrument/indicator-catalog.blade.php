<div>
    @php
        $catalogYear = session('indicator_year');
        $catalogYearAno = $catalogYear instanceof \App\Models\Year ? $catalogYear->ano : null;
    @endphp

    @if (session()->has('catalog_warning'))
        <div class="alert alert-warning alert-dismissible no-print" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            <i class="voyager-warning"></i> {{ session('catalog_warning') }}
        </div>
    @endif

    @if (session()->has('catalog_message'))
        <div class="alert alert-success alert-dismissible no-print" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('catalog_message') }}
        </div>
    @endif

    @if ($sortMode)
        <div class="row metas-catalog-toolbar no-print">
            <div class="col-md-12 text-right">
                <button wire:click="exitSortMode" type="button" class="btn btn-default">
                    <i class="voyager-angle-left"></i> Listo
                </button>
            </div>
        </div>
        @include('livewire.metas-instrument.partials.indicator-catalog-sort', [
            'indicators' => $indicators,
        ])
    @else
        <div class="row metas-catalog-toolbar no-print">
            <div class="col-md-5">
                <input wire:model.live.debounce.300ms="search" type="text" class="form-control"
                    placeholder="Buscar por clave o nombre del indicador..." />
            </div>
            <div class="col-md-3">
                <select wire:model.live.debounce.300ms="ambitoFilter" class="form-control">
                    <option value="">Todos los ámbitos</option>
                    <option value="none">Sin ámbito</option>
                    @foreach ($indicatorAmbitoOptions as $ambito)
                        <option value="{{ $ambito->id }}">{{ $ambito->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 text-right">
                <x-metas.print-button class="metas-carga-btn-spaced" />
                <x-metas.copy-image-button target="#metas-capture-catalog" class="metas-carga-btn-spaced" />
                @if (auth()->user()->hasRole(['parametrizador', 'admin']))
                    <button wire:click="enterSortMode" type="button" class="btn btn-success metas-carga-btn-spaced">
                        <i class="voyager-list"></i> Ordenar
                    </button>
                    <a href="{{ route('metas.instrument.indicators.create', $metasInstrument) }}"
                        class="btn btn-primary">
                        <i class="voyager-plus"></i> Nuevo
                    </a>
                @endif
            </div>
        </div>

        <div id="metas-capture-catalog" class="metas-capture-root">
            <div class="metas-print-header print-only">
                <h2>
                    Catálogo
                    @if ($catalogYearAno)
                        — {{ $catalogYearAno }}
                    @endif
                </h2>
                @if ($search !== '')
                    <p>Filtro activo: «{{ $search }}»</p>
                @endif
            </div>

            <table
                class="table table-bordered table-hover table-striped metas-instrument-table metas-instrument-indicator-catalog metas-catalog-table">
                <thead>
                    <tr>
                        <th class="text-center metas-catalog-col--clave metas-catalog-col--nowrap">Clave</th>
                        <th class="metas-catalog-col--nombre">Nombre</th>
                        @if (
                            $showsOperationalColumn ??
                                auth()->user()->hasRole(['control-gestion', 'admin', 'referente', 'visualizador']))
                            <th class="text-center metas-catalog-col--actualizado">Actualizado a</th>
                            <th class="text-center metas-catalog-col--cumplimiento">Cumplimiento promedio</th>
                        @endif
                        <th class="text-center metas-catalog-col--acciones no-print">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($indicators as $indicator)
                        @php
                            $showsOperational =
                                $showsOperationalColumn ??
                                auth()->user()->hasRole(['control-gestion', 'admin', 'referente', 'visualizador']);
                            $catalogAmbitos = $indicator->indicatorAmbitos ?? collect();
                            $catalogReferentes = ($indicator->referentes ?? collect())->filter(
                                fn ($ref) => $ref->user !== null,
                            );
                            $formulaIssue = $formulaIssuesByIndicator->get($indicator->id);
                            $ultimoMes = $showsOperational
                                ? $lastLoadedMonthByIndicator->get($indicator->id)
                                : null;
                            $configStatus = $showsOperational
                                ? $fieldSsConfigStatusByIndicator->get($indicator->id)
                                : null;
                            $configIncompleta =
                                $showsOperational &&
                                in_array(
                                    $configStatus,
                                    [
                                        \App\Services\Metas\MetFieldSsConfigService::STATUS_NONE,
                                        \App\Services\Metas\MetFieldSsConfigService::STATUS_PARTIAL,
                                    ],
                                    true,
                                );
                            $tieneErrorCalculo =
                                $showsOperational && $indicatorsWithCalculationErrors->contains($indicator->id);
                            $tienePeriodoIncompleto =
                                $showsOperational && $indicatorsWithIncompletePeriods->contains($indicator->id);
                            $sinDatos = $showsOperational && $ultimoMes === null;
                            $mesEtiqueta =
                                $ultimoMes !== null
                                    ? \App\Enums\Metas\MetInstrumentPeriodo::tryFrom(
                                            sprintf('MES-%02d', $ultimoMes),
                                        )?->etiquetaCorta() ?? (string) $ultimoMes
                                    : null;
                            $configDescription =
                                $configStatus === \App\Services\Metas\MetFieldSsConfigService::STATUS_PARTIAL
                                    ? 'Tipos de entrada pendientes para su Servicio de Salud.'
                                    : 'Sin configuración de tipos de entrada/agregación por SS para este indicador.';
                            $errorCalculoDescription = $mesEtiqueta
                                ? 'Actualizado a '.$mesEtiqueta.' · Revise datos cargados o desempeño por establecimiento'
                                : 'Existen resultados con error de cálculo para este indicador en su Servicio de Salud.';
                            $periodoIncompletoDescription = $mesEtiqueta
                                ? 'Actualizado a '.$mesEtiqueta.' · Revise meses con captura parcial en carga o desempeño'
                                : 'Existen períodos con captura parcial para este indicador en su Servicio de Salud.';
                            $hasCatalogAlerts =
                                ($catalogAmbitos->isEmpty() &&
                                    auth()->user()->hasRole(['parametrizador', 'admin'])) ||
                                ($catalogReferentes->isEmpty() &&
                                    auth()->user()->hasRole(['control-gestion', 'admin'])) ||
                                (bool) $formulaIssue ||
                                $configIncompleta ||
                                $tieneErrorCalculo ||
                                $tienePeriodoIncompleto ||
                                $sinDatos;
                        @endphp
                        <tr wire:key="indicator-{{ $indicator->id }}">
                            <td class="text-center metas-catalog-col--clave metas-catalog-col--nowrap">
                                <code>{{ $indicator->clave }}</code>
                                @if (auth()->user()->hasRole(['parametrizador', 'admin']))
                                    <div class="metas-catalog-duplicate-action no-print">
                                        <button type="button" wire:click="openDuplicateModal({{ $indicator->id }})"
                                            class="btn btn-xs btn-primary" title="Duplicar indicador">
                                            <i class="voyager-brush"></i></button>
                                    </div>
                                @endif
                            </td>
                            <td class="metas-catalog-col--nombre">
                                {{ $indicator->nombre }}
                                @if (
                                    !$indicator->estado &&
                                        auth()->user()->hasRole(['parametrizador', 'admin']))
                                    <span
                                        class="label label-default metas-label-normal metas-label-spaced">Inactivo</span>
                                @endif
                                @if ($hasCatalogAlerts)
                                    <div class="metas-catalog-alerts no-capture no-print">
                                        @if (
                                            $catalogAmbitos->isEmpty() &&
                                                auth()->user()->hasRole(['parametrizador', 'admin']))
                                            <x-metas.catalog-alert-tag class="metas-catalog-ambito-alert"
                                                title="Sin ámbito asignado"
                                                description="Este indicador no tiene ámbitos asignados." />
                                        @endif
                                        @if (
                                            $catalogReferentes->isEmpty() &&
                                                auth()->user()->hasRole(['control-gestion', 'admin']))
                                            <x-metas.catalog-alert-tag class="metas-catalog-referentes-alert"
                                                title="Sin referentes asignados"
                                                description="Este indicador no tiene referentes asignados." />
                                        @endif
                                        @if ($formulaIssue)
                                            <x-metas.catalog-alert-tag class="metas-formula-issue-alert"
                                                title="Fórmula incompleta"
                                                :description="$formulaIssue['message'] ??
                                                    'Este indicador no puede calcular resultados hasta corregir su fórmula.'" />
                                        @endif
                                        @if ($configIncompleta)
                                            <x-metas.catalog-alert-tag class="metas-catalog-config-alert"
                                                title="Carga de datos" :description="$configDescription" />
                                        @endif
                                        @if ($tieneErrorCalculo)
                                            <x-metas.catalog-alert-tag class="metas-calculation-error-alert"
                                                title="Error en resultados" :description="$errorCalculoDescription" />
                                        @endif
                                        @if ($tienePeriodoIncompleto)
                                            <x-metas.catalog-alert-tag class="metas-incomplete-period-alert"
                                                title="Carga incompleta" :description="$periodoIncompletoDescription" />
                                        @endif
                                        @if ($sinDatos)
                                            <x-metas.catalog-alert-tag class="metas-catalog-sin-datos-alert"
                                                title="Sin datos"
                                                description="Aún no hay meses con datos cargados para este indicador en su Servicio de Salud." />
                                        @endif
                                    </div>
                                @endif
                            </td>
                            @if ($showsOperational)
                                <td class="text-center metas-catalog-col--actualizado">
                                    @if ($mesEtiqueta)
                                        <span class="label label-success metas-catalog-actualizado-label">
                                            {{ $mesEtiqueta }}
                                        </span>
                                    @else
                                        <span class="metas-catalog-actualizado-empty">—</span>
                                    @endif
                                </td>
                                <td class="text-center metas-catalog-col--cumplimiento">
                                    @php
                                        $promedioCumplimiento = $promedioCumplimientoByIndicator->get($indicator->id);
                                        $promedioCumplClass =
                                            $promedioCumplimiento !== null
                                                ? \App\Support\Metas\MetSemaforoThresholds::labelClassForIndicatorCompliance(
                                                    $promedioCumplimiento,
                                                    $indicator->umbral_superior !== null
                                                        ? (float) $indicator->umbral_superior
                                                        : null,
                                                    $indicator->umbral_inferior !== null
                                                        ? (float) $indicator->umbral_inferior
                                                        : null,
                                                )
                                                : 'default';
                                    @endphp
                                    @if ($promedioCumplimiento !== null)
                                        <span
                                            class="label label-{{ $promedioCumplClass }} metas-catalog-cumplimiento-label">
                                            {{ number_format($promedioCumplimiento, 2) }}%
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endif
                            <td class="text-center metas-catalog-col--acciones no-print">
                                <div class="metas-catalog-acciones-wrap">
                                    @if (auth()->user()->hasRole(['control-gestion', 'admin', 'referente', 'visualizador']))
                                        <a href="{{ route('metas.instrument.indicators.show', [$metasInstrument, $indicator]) }}"
                                            class="btn btn-xs btn-warning" title="Ver indicador">
                                            <i class="voyager-info-circled"></i> Ver indicador
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showsOperationalColumn ??auth()->user()->hasRole(['control-gestion', 'admin', 'referente', 'visualizador'])? 5: 3 }}"
                                class="text-center text-muted">
                                No hay indicadores registrados para el año activo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="metas-catalog-pagination text-center no-print">
            {{ $indicators->links() }}
        </div>
    @endif

    {{-- Modales auxiliares del catálogo (dentro del árbol Livewire; estilos en custom.css + display inline) --}}
    @include('livewire.metas-instrument.indicator-catalog-duplicate-modal')
</div>

@script
    <script>
        window.initMetasIndicatorCatalogSort = function initMetasIndicatorCatalogSort(attempt = 0) {
            const $container = jQuery('#metas-indicator-catalog-sort.dd');

            if (!$container.length || typeof jQuery.fn.nestable !== 'function') {
                if (attempt < 20) {
                    setTimeout(() => window.initMetasIndicatorCatalogSort(attempt + 1), 50);
                }

                return;
            }

            if ($container.data('nestable')) {
                $container.nestable('destroy');
            }

            $container.nestable({
                maxDepth: 1,
            });

            $container.off('change.metasCatalogSort').on('change.metasCatalogSort', function() {
                const serialized = $container.nestable('serialize') || [];
                const ids = serialized.map((item) => parseInt(item.id, 10)).filter((id) => !Number.isNaN(id));

                if (ids.length > 0) {
                    $wire.updateIndicatorOrder(ids);
                }
            });
        };

        $wire.on('catalog-sort-saved', () => {
            if (typeof toastr !== 'undefined') {
                toastr.success(@json(__('voyager::bread.updated_order')));
            }
        });
    </script>
@endscript
