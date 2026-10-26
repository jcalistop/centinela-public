<?php

namespace App\Support\Comges;

use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\User;
use App\Models\Year;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ComIndicatorCommitmentRelocation
{
    /**
     * Reubicación de compromiso: mismo criterio que editar el indicador en Voyager BREAD.
     */
    public static function userCanChangeCommitmentPlacement(User $user, ComIndicator $indicator): bool
    {
        return $user->can('edit', $indicator);
    }

    /**
     * @throws ValidationException
     */
    public static function guardUpdateRequest(Request $request, User $user, ComIndicator $indicator, ?Year $sessionYear): void
    {
        if (! self::userCanChangeCommitmentPlacement($user, $indicator)) {
            $request->merge(['com_commitment_id' => $indicator->com_commitment_id]);

            return;
        }

        self::assertValidCommitmentId($request->input('com_commitment_id'), $sessionYear);
    }

    /**
     * @throws ValidationException
     */
    private static function assertValidCommitmentId(mixed $commitmentId, ?Year $sessionYear): void
    {
        if (! $sessionYear instanceof Year) {
            throw ValidationException::withMessages([
                'com_commitment_id' => 'No hay año de sesión activo para validar el compromiso.',
            ]);
        }

        if ($commitmentId === null || $commitmentId === '') {
            throw ValidationException::withMessages([
                'com_commitment_id' => 'Debe seleccionar un compromiso.',
            ]);
        }

        $exists = ComCommitment::query()
            ->whereKey($commitmentId)
            ->where('year_id', $sessionYear->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'com_commitment_id' => 'El compromiso seleccionado no pertenece al año activo o no existe.',
            ]);
        }
    }
}
