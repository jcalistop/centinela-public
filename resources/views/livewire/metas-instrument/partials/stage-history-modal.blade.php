@if ($showStageHistoryModal)
    <div class="modal fade in metas-instrument-ui admin-dashboard-compass admin-dashboard-compass--modal metas-stage-history-modal"
        style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1" role="dialog"
        aria-labelledby="metas-stage-history-modal-title" wire:key="metas-stage-history-modal">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" wire:click="closeStageHistoryModal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="metas-stage-history-modal-title">
                        <i class="voyager-list" aria-hidden="true"></i>
                        Historial de cambios de etapa
                        @if (($stageTransitionsCount ?? 0) > 0)
                            <span class="label label-default metas-stage-history-modal__count">
                                {{ $stageTransitionsCount }}
                            </span>
                        @endif
                    </h4>
                </div>

                <div class="modal-body metas-stage-history-modal__body">
                    @if ($stageTransitions->isEmpty())
                        <p class="indicator-bitacora-empty">No hay registros de transición para este instrumento.</p>
                    @else
                        <p class="metas-stage-history-modal__intro text-muted">
                            Cambios de etapa registrados para su Servicio de Salud en el año activo, ordenados del más
                            reciente al más antiguo.
                        </p>

                        <div class="metas-stage-history-modal__table-wrap table-responsive"
                            wire:loading.class="metas-stage-history-modal__table-wrap--loading">
                            <table
                                class="table table-bordered table-condensed cen-compass-table metas-stage-history-table">
                                <colgroup>
                                    <col class="metas-stage-history-table__col-date">
                                    <col class="metas-stage-history-table__col-stage">
                                    <col class="metas-stage-history-table__col-stage">
                                    <col class="metas-stage-history-table__col-motivo">
                                    <col class="metas-stage-history-table__col-user">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Desde</th>
                                        <th>Hacia</th>
                                        <th>Motivo</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($stageTransitions as $transition)
                                        <tr wire:key="stage-transition-{{ $transition->id }}">
                                            <td class="text-nowrap metas-stage-history-table__date">
                                                {{ $transition->created_at?->format('d/m/Y H:i') ?? '—' }}
                                            </td>
                                            <td>
                                                @if ($transition->etapa_desde)
                                                    <span class="label label-default label-sm">
                                                        {{ $transition->etapa_desde->label() }}
                                                    </span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                <span class="label label-info label-sm">
                                                    {{ $transition->etapa_hasta->label() }}
                                                </span>
                                            </td>
                                            <td class="metas-stage-history-table__motivo">
                                                {{ $transition->motivo ?: '—' }}
                                            </td>
                                            <td class="metas-stage-history-table__user">
                                                {{ $transition->user?->name ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($stageTransitions->hasPages())
                            {{ $stageTransitions->links('vendor.livewire.stage-history-pagination', [
                                'scrollTo' => '.metas-stage-history-modal__table-wrap',
                            ]) }}
                        @else
                            <p class="metas-stage-history-modal__summary text-muted">
                                Mostrando los <strong>{{ $stageTransitionsCount }}</strong>
                                {{ $stageTransitionsCount === 1 ? 'registro' : 'registros' }}.
                            </p>
                        @endif
                    @endif
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" wire:click="closeStageHistoryModal">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
