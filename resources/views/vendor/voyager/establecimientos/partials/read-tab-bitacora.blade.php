@php
    $center = $dataTypeContent;
    $notesCount = ($notes ?? collect())->count();
    $hasPendingTask = (bool) $center->tarea_pendiente;
@endphp
<div class="cen-read-detail">
    <div class="cen-read-spotlight @if ($hasPendingTask) cen-read-spotlight--pending @endif">
        <p class="cen-read-spotlight__label">Bitácora local</p>
        <p class="cen-read-spotlight__value">{{ $center->nombre_corto ?? $center->codigo }}</p>
        <p class="cen-read-spotlight__hint">
            Notas operativas de Centinela para <strong>{{ $center->codigo }}</strong>.
            No modifican los datos oficiales del catálogo nacional (datos.gob.cl).
        </p>
        @if ($hasPendingTask)
            <p style="margin:10px 0 0;">
                <span class="cen-read-badge cen-read-badge--warning">
                    <i class="voyager-warning"></i> Tarea pendiente activa
                </span>
            </p>
        @endif
    </div>

    <div class="cen-read-section">
        <div class="cen-read-section__head">
            <i class="voyager-edit"></i>
            <h4 class="cen-read-section__title">Registrar nueva nota</h4>
        </div>
        <div class="cen-read-form-box">
            <form method="post" action="{{ route('voyager.establecimientos.notes.store', $center->getKey()) }}">
                @csrf
                <input type="hidden" name="tab" value="bitacora">
                <div class="form-group">
                    <label for="note_body">Contenido</label>
                    <textarea name="body" id="note_body" class="form-control" rows="4" maxlength="5000"
                        placeholder="Ej.: acuerdo interno, contacto territorial, observación para el equipo…"></textarea>
                    <p class="help-block" style="margin-bottom:0;font-size:12px;">
                        Obligatorio si no cambia el estado de tarea pendiente. Al activar o desactivar el toggle, se
                        registra automáticamente en la bitácora.
                    </p>
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="cen-read-toggle">
                        <input type="hidden" name="tarea_pendiente" value="0">
                        <input type="checkbox" name="tarea_pendiente" value="1" @checked($hasPendingTask)>
                        <span class="cen-read-toggle__track" aria-hidden="true"></span>
                        <span class="cen-read-toggle__text">Tarea pendiente</span>
                    </label>
                    <p class="help-block" style="margin:8px 0 0;font-size:12px;">
                        Indica si el establecimiento tiene una tarea operativa pendiente. Al marcar o desmarcar respecto
                        del estado actual, se genera un registro en la bitácora.
                    </p>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="voyager-check"></i> Guardar nota
                </button>
            </form>
        </div>
    </div>

    <div class="cen-read-section">
        <div class="cen-read-section__head">
            <i class="voyager-book"></i>
            <h4 class="cen-read-section__title">
                Notas registradas
                @if ($notesCount > 0)
                    <span class="cen-read-count-badge">{{ $notesCount }}</span>
                @endif
            </h4>
        </div>
        @if ($notesCount === 0)
            <div class="cen-read-empty">
                <i class="voyager-book"></i>
                <p><strong>Sin notas</strong></p>
                <p style="font-size:13px;max-width:360px;margin:0 auto;">
                    Aún no hay entradas en la bitácora de este establecimiento.
                </p>
            </div>
        @else
            <ul class="cen-read-note-list">
                @foreach ($notes as $note)
                    @php
                        $noteKind = $note->note_kind ?? 'user';
                        $cardClass = match ($noteKind) {
                            'pending_marked' => 'cen-read-note-card--pending-on',
                            'pending_cleared' => 'cen-read-note-card--pending-off',
                            default => '',
                        };
                        $kindBadge = match ($noteKind) {
                            'pending_marked' => ['Tarea pendiente', 'warning'],
                            'pending_cleared' => ['Pendiente cerrada', 'success'],
                            default => null,
                        };
                    @endphp
                    <li class="cen-read-note-card {{ $cardClass }}">
                        <div class="cen-read-note-card__meta">
                            <span class="cen-read-note-card__date">
                                <i class="voyager-calendar"></i>
                                {{ $note->created_at?->format('d/m/Y H:i') }}
                            </span>
                            <span class="cen-read-note-card__user">
                                <i class="voyager-person"></i>
                                {{ $note->user?->name ?? 'Usuario' }}
                            </span>
                            @if ($kindBadge)
                                <span
                                    class="cen-read-badge cen-read-badge--{{ $kindBadge[1] }}">{{ $kindBadge[0] }}</span>
                            @endif
                        </div>
                        <p class="cen-read-note-card__body">{{ $note->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
