<div class="comges-structure-order">
    <div class="alert alert-info comges-structure-order-hint" role="status">
        <i class="voyager-info-circled"></i>
        {{ __('voyager::generic.drag_drop_info') }}
        Arrastre <strong>indicadores</strong> dentro de un compromiso o hacia otro del mismo año.
        Colapse los compromisos para recorrer listas largas; al arrastrar sobre uno colapsado se expande.
        Acerque el ítem al borde de la pantalla para desplazar el listado.
    </div>

    <div id="comges-structure-sort-root" wire:ignore>
        @forelse ($commitments as $commitment)
            @php
                $panelId = 'comges-structure-commitment-' . $commitment->id;
                $indicatorCount = $commitment->comIndicators->count();
            @endphp
            <section class="cen-dashboard-block comges-structure-commitment-panel"
                data-commitment-panel="{{ $commitment->id }}">
                <header class="cen-dashboard-block__head comges-structure-panel-heading" role="button" tabindex="0"
                    data-toggle="collapse" data-target="#{{ $panelId }}" aria-expanded="false"
                    aria-controls="{{ $panelId }}">
                    <div class="cen-dashboard-block__head-main comges-structure-panel-heading__main">
                        <span class="comges-structure-chevron" aria-hidden="true">
                            <i class="glyphicon glyphicon-chevron-right"></i>
                        </span>
                        <div class="comges-structure-panel-heading__text">
                            <span class="comges-structure-commitment-clave">{{ $commitment->clave }}</span>
                            <h3 class="cen-dashboard-block__title comges-structure-commitment-title">
                                {{ $commitment->nombre }}
                            </h3>
                            <span class="comges-structure-collapsed-hint">Clic o arrastre aquí para expandir</span>
                        </div>
                    </div>
                    <span class="badge comges-structure-indicator-count">
                        {{ $indicatorCount }}
                        indicador{{ $indicatorCount === 1 ? '' : 'es' }}
                    </span>
                </header>
                <div id="{{ $panelId }}" class="panel-collapse collapse">
                    <div class="comges-structure-panel-body">
                        <div class="comges-structure-sort-list" data-commitment-id="{{ $commitment->id }}">
                            @foreach ($commitment->comIndicators as $indicator)
                                <div class="comges-structure-sort-item" data-id="{{ $indicator->id }}">
                                    <span class="comges-sort-handle" title="Arrastrar para reordenar"
                                        aria-hidden="true">
                                        <i class="voyager-drag"></i>
                                    </span>
                                    <div class="comges-structure-sort-item__content">
                                        <span class="comges-structure-indicator-clave">{{ $indicator->clave }}</span>
                                        <span class="comges-structure-indicator-nombre">{{ $indicator->nombre }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($commitment->comIndicators->isEmpty())
                            <p class="comges-structure-empty-hint">
                                Sin indicadores — puede soltar aquí indicadores de otros compromisos.
                            </p>
                        @endif
                    </div>
                </div>
            </section>
        @empty
            <p class="text-muted">No hay compromisos para el año activo.</p>
        @endforelse
    </div>
</div>

@include('livewire.comges.partials.structure-order-styles')
