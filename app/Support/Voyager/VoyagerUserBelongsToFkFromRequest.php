<?php

namespace App\Support\Voyager;

use App\Models\User;
use Illuminate\Http\Request;
use TCG\Voyager\Http\Controllers\Controller;

/**
 * Voyager {@see Controller::insertUpdateData} ignora relaciones belongsTo del BREAD; el formulario
 * publicado envía claves virtuales (p. ej. user_belongsto_role_relationship). Aquí persistimos
 * las columnas FK correspondientes.
 */
final class VoyagerUserBelongsToFkFromRequest
{
    public static function sync(User $user, Request $request): bool
    {
        $changed = false;
        $actor = VoyagerUserActorAuthorization::authenticatedActor();

        $roleInput = null;
        if ($request->has('role_id')) {
            $roleInput = $request->input('role_id');
        } elseif ($request->has('user_belongsto_role_relationship')) {
            $roleInput = $request->input('user_belongsto_role_relationship');
        }
        if ($roleInput !== null) {
            $roleId = self::positiveInt($roleInput);
            if ($roleId !== null) {
                // Autoedición de perfil: el rol del request es el propio del usuario (lo re-inyecta Voyager).
                // No aplicar los guards de gestión/asignación (lanzarían 403); preservar el rol propio evita que
                // role_id quede NULL y el usuario pierda browse_admin (bucle ERR_TOO_MANY_REDIRECTS).
                $isSelfEdit = $actor instanceof User
                    && $user->exists
                    && (int) $actor->getKey() === (int) $user->getKey();

                if ($actor instanceof User && ! $isSelfEdit) {
                    VoyagerUserActorAuthorization::assertCanManageUser($actor, $user);
                    VoyagerUserActorAuthorization::assertCanAssignRole($actor, $roleId);
                }

                $user->role_id = $roleId;
                $changed = true;
            }
        }

        $cenHieInput = null;
        if ($request->has('cen_hie_dependency_id')) {
            $cenHieInput = $request->input('cen_hie_dependency_id');
        } elseif ($request->has('user_belongsto_cen_hie_dependency_relationship')) {
            $cenHieInput = $request->input('user_belongsto_cen_hie_dependency_relationship');
        }
        if ($cenHieInput !== null) {
            $dependencyId = self::positiveInt($cenHieInput);
            if ($dependencyId !== null) {
                $user->cen_hie_dependency_id = $dependencyId;
                $changed = true;
            }
        }

        if ($changed) {
            $user->save();
        }

        return $changed;
    }

    /**
     * @param  mixed  $value
     */
    private static function positiveInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
