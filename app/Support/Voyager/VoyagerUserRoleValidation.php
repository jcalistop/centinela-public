<?php

namespace App\Support\Voyager;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Valida el FK de rol cuando el formulario muestra el selector (permiso edit_users / gate editRoles).
 */
final class VoyagerUserRoleValidation
{
    /**
     * Alinea el POST con {@see VoyagerUserBelongsToFkFromRequest} ante nombres de campo distintos.
     */
    public static function validate(Request $request): void
    {
        if (! $request->filled('role_id') && $request->filled('user_belongsto_role_relationship')) {
            $request->merge([
                'role_id' => $request->input('user_belongsto_role_relationship'),
            ]);
        }

        Validator::make(
            $request->all(),
            [
                'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            ],
            [
                'role_id.required' => 'El rol de usuario es obligatorio.',
                'role_id.integer' => 'El rol seleccionado no es válido.',
                'role_id.exists' => 'El rol seleccionado no es válido.',
            ]
        )->validate();

        self::validateAssignableRole($request);
    }

    public static function validateAssignableRole(Request $request): void
    {
        $actor = VoyagerUserActorAuthorization::authenticatedActor();
        if (! $actor instanceof User) {
            return;
        }

        $role = VoyagerUserActorAuthorization::resolveRoleFromRequest($request);
        if ($role === null) {
            return;
        }

        if (! VoyagerUserActorAuthorization::canAssignRole($actor, $role)) {
            throw ValidationException::withMessages([
                'role_id' => 'No tiene permiso para asignar este rol.',
            ]);
        }
    }
}
