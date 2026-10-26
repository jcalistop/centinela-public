<?php

namespace App\Services\Cen;

use App\Models\CenCenter;
use App\Models\CenCenterNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CenCenterBitacoraService
{
    public const NOTE_KIND_USER = 'user';

    public const NOTE_KIND_PENDING_MARKED = 'pending_marked';

    public const NOTE_KIND_PENDING_CLEARED = 'pending_cleared';

    /**
     * @throws ValidationException
     */
    public function storeNote(CenCenter $center, User $user, ?string $body, bool $wantsPending): CenCenterNote
    {
        $trimmedBody = trim($body ?? '');
        $wasPending = (bool) $center->tarea_pendiente;
        $stateChanged = $wantsPending !== $wasPending;

        if (! $stateChanged && $trimmedBody === '') {
            throw ValidationException::withMessages([
                'body' => 'Ingrese el contenido de la nota o cambie el estado de tarea pendiente.',
            ]);
        }

        return DB::transaction(function () use ($center, $user, $trimmedBody, $wantsPending, $stateChanged): CenCenterNote {
            if ($stateChanged) {
                $center->update(['tarea_pendiente' => $wantsPending]);

                return $this->createNote(
                    $center,
                    $user,
                    $this->buildPendingStateNoteBody($wantsPending, $trimmedBody),
                    $wantsPending ? self::NOTE_KIND_PENDING_MARKED : self::NOTE_KIND_PENDING_CLEARED,
                );
            }

            return $this->createNote($center, $user, $trimmedBody, self::NOTE_KIND_USER);
        });
    }

    private function buildPendingStateNoteBody(bool $markedPending, string $userBody): string
    {
        $header = $markedPending
            ? '【Tarea pendiente】 Se registró una tarea pendiente para este establecimiento.'
            : '【Tarea pendiente】 Se dejó de registrar tarea pendiente (resuelta o sin pendientes).';

        if ($userBody === '') {
            return $header;
        }

        return $header."\n\n".$userBody;
    }

    private function createNote(CenCenter $center, User $user, string $body, string $kind): CenCenterNote
    {
        return CenCenterNote::query()->create([
            'cen_center_id' => $center->id,
            'user_id' => $user->id,
            'body' => $body,
            'note_kind' => $kind,
        ]);
    }
}
