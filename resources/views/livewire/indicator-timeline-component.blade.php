<div class="metas-tab-panel metas-bitacora-panel">
    @if (session('indicator_timeline_success'))
        <div class="alert alert-success metas-bitacora-alert">{{ session('indicator_timeline_success') }}</div>
    @endif

    @if ($canAddNote)
        <form wire:submit="addNote" class="cen-dashboard-block metas-bitacora-add-note">
            <header class="cen-dashboard-block__head metas-bitacora-add-note__heading" data-toggle="collapse"
                data-target="#indicator-add-note-collapse-{{ $indicatorId }}" aria-expanded="false" role="button"
                tabindex="0">
                <div class="cen-dashboard-block__head-main">
                    <h3 class="cen-dashboard-block__title">
                        <i class="glyphicon glyphicon-chevron-right metas-bitacora-add-note__chevron"
                            aria-hidden="true"></i>
                        <i class="voyager-edit metas-bitacora-add-note__chevron--voyager" aria-hidden="true"></i>
                        Agregar nota
                    </h3>
                </div>
            </header>
            <div id="indicator-add-note-collapse-{{ $indicatorId }}" class="panel-collapse collapse">
                <div class="cen-dashboard-block__body">
                    <div class="form-group @error('note') has-error @enderror">
                        <label for="indicator-note">Nota informativa</label>
                        <textarea id="indicator-note" wire:model="note" class="form-control" rows="3"
                            placeholder="Escribe una nota para la bitácora del indicador..."></textarea>
                        @error('note')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" wire:model="notifyReferentes">
                            Notificar a referentes
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" wire:model="mantenerDestacada">
                            Mantener destacada
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="addNote">Agregar nota</span>
                        <span wire:loading wire:target="addNote">Guardando...</span>
                    </button>
                </div>
            </div>
        </form>
    @elseif ($closedCutoffMessage)
        <div class="alert alert-warning metas-bitacora-alert">{{ $closedCutoffMessage }}</div>
    @endif

    <section class="cen-dashboard-block indicator-bitacora-block">
        <header class="cen-dashboard-block__head">
            <div class="cen-dashboard-block__head-main">
                <h3 class="cen-dashboard-block__title">
                    <i class="voyager-logbook" aria-hidden="true"></i>
                    Bitácora
                </h3>
            </div>
        </header>
        <div class="cen-dashboard-block__body">
            @forelse ($activities as $activity)
                <article @class([
                    'indicator-bitacora-entry',
                    'indicator-bitacora-entry--pinned' => $activity->is_pinned,
                ]) wire:key="activity-{{ $activity->id }}">
                    <div class="indicator-bitacora-entry__icon" aria-hidden="true">
                        <i class="{{ $activity->action->iconClass() }}"></i>
                    </div>
                    <div class="indicator-bitacora-entry__body">
                        <div class="indicator-bitacora-entry__row">
                            <div class="indicator-bitacora-entry__content">
                                @if ($activity->is_pinned)
                                    <span class="label label-warning indicator-bitacora-entry__label">
                                        <i class="voyager-star"></i> Destacada
                                    </span>
                                @endif
                                <span
                                    class="label indicator-bitacora-entry__label {{ $activity->action === \App\Enums\Indicators\IndicatorActivityAction::ManualNote ? 'label-warning' : 'label-default' }}">
                                    {{ $activity->action->label() }}
                                </span>
                                <span>{{ $activity->description }}</span>
                            </div>
                            <div class="indicator-bitacora-entry__actions">
                                @can('update', $activity)
                                    @if ($activity->is_pinned)
                                        <button type="button" class="btn btn-xs btn-default"
                                            wire:click="togglePinned({{ $activity->id }})"
                                            wire:confirm="¿Quitar destacado de esta nota?">
                                            <i class="voyager-star-two"></i> Quitar destacado
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-xs btn-warning"
                                            wire:click="togglePinned({{ $activity->id }})">
                                            <i class="voyager-star"></i> Destacar
                                        </button>
                                    @endif
                                @endcan
                                @can('delete', $activity)
                                    <button type="button" class="btn btn-xs btn-danger"
                                        wire:click="deleteNote({{ $activity->id }})"
                                        wire:confirm="¿Eliminar esta nota manual?">
                                        Eliminar
                                    </button>
                                @endcan
                            </div>
                        </div>
                        <div class="indicator-bitacora-entry__meta">
                            <i class="voyager-person"></i>
                            {{ $activity->user?->name ?? 'Sistema' }}
                            ·
                            {{ $activity->created_at?->format('d-m-Y H:i') }}
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-muted indicator-bitacora-empty">Sin actividades registradas.</p>
            @endforelse

            {{ $activities->links() }}
        </div>
    </section>
</div>
