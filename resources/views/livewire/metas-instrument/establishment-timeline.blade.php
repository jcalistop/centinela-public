<div style="display: inline-block;">
    <button type="button" class="btn btn-warning btn-sm" wire:click="openModal" wire:loading.attr="disabled">
        <i class="voyager-logbook" aria-hidden="true"></i>
        Ver bitácora
    </button>

    @if ($showModal)
        <div class="modal fade in metas-instrument-ui admin-dashboard-compass admin-dashboard-compass--modal metas-establishment-timeline-modal"
            style="display:block; background:rgba(0,0,0,0.5);" tabindex="-1" role="dialog"
            aria-labelledby="metas-establishment-timeline-title" wire:key="metas-establishment-timeline-modal">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeModal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="metas-establishment-timeline-title">
                            <i class="voyager-logbook" aria-hidden="true"></i>
                            Bitácora del establecimiento
                            @if ($events->total() > 0)
                                <span
                                    class="label label-default metas-establishment-timeline-modal__count">{{ $events->total() }}</span>
                            @endif
                        </h4>
                    </div>

                    <div class="modal-body metas-establishment-timeline-modal__body">
                        <p class="text-muted metas-establishment-timeline-modal__intro">
                            Historial del ciclo del convenio en este centro (envíos, reaperturas y apelaciones),
                            ordenado del más reciente al más antiguo.<br>
                            Las apelaciones también permanecen en la bitácora de cada indicador.
                        </p>

                        <div wire:loading.flex wire:target="openModal,gotoPage,nextPage,previousPage" class="text-muted"
                            style="margin-bottom: 0.75rem;">
                            Cargando…
                        </div>

                        <section class="cen-dashboard-block indicator-bitacora-block"
                            wire:loading.class="metas-stage-history-modal__table-wrap--loading">
                            <div class="cen-dashboard-block__body">
                                @forelse ($events as $event)
                                    <article class="indicator-bitacora-entry" wire:key="{{ $event['key'] }}">
                                        <div class="indicator-bitacora-entry__icon" aria-hidden="true">
                                            @if ($event['type'] === 'agreement_submitted')
                                                <i class="voyager-check"></i>
                                            @elseif ($event['type'] === 'agreement_reopened')
                                                <i class="voyager-refresh"></i>
                                            @else
                                                <i class="voyager-bubble"></i>
                                            @endif
                                        </div>
                                        <div class="indicator-bitacora-entry__body">
                                            <div class="indicator-bitacora-entry__row">
                                                <div class="indicator-bitacora-entry__content">
                                                    <div class="indicator-bitacora-entry__title-row">
                                                        <span @class([
                                                            'label',
                                                            'indicator-bitacora-entry__label',
                                                            'label-success' => $event['type'] === 'agreement_submitted',
                                                            'label-warning' => $event['type'] === 'agreement_reopened',
                                                            'label-info' => str_starts_with((string) $event['type'], 'appeal_'),
                                                        ])>
                                                            {{ $event['title'] }}
                                                        </span>
                                                    </div>
                                                    @if ($event['indicator_label'])
                                                        <div class="indicator-bitacora-entry__indicator">
                                                            @if ($event['indicator_url'])
                                                                <a
                                                                    href="{{ $event['indicator_url'] }}">{{ $event['indicator_label'] }}</a>
                                                            @else
                                                                <span>{{ $event['indicator_label'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if ($event['detail'])
                                                        <p class="indicator-bitacora-entry__detail">
                                                            {{ $event['detail'] }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="indicator-bitacora-entry__meta">
                                                <i class="voyager-person"></i>
                                                {{ $event['user_name'] ?? 'Sistema' }}
                                                ·
                                                {{ $event['occurred_at']->format('d-m-Y H:i') }}
                                            </div>
                                        </div>
                                    </article>
                                @empty
                                    <p class="text-muted indicator-bitacora-empty">
                                        No hay eventos registrados para este establecimiento en el año activo.
                                    </p>
                                @endforelse

                                @if ($events->hasPages())
                                    <div class="metas-establishment-timeline-modal__pagination">
                                        {{ $events->links('vendor.livewire.stage-history-pagination', [
                                            'scrollTo' => '.metas-establishment-timeline-modal__body',
                                        ]) }}
                                    </div>
                                @elseif ($events->total() > 0)
                                    <p class="text-muted metas-establishment-timeline-modal__summary">
                                        Mostrando los <strong>{{ $events->total() }}</strong>
                                        {{ $events->total() === 1 ? 'registro' : 'registros' }}.
                                    </p>
                                @endif
                            </div>
                        </section>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" wire:click="closeModal">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
