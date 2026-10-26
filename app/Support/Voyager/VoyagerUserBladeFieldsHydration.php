<?php

namespace App\Support\Voyager;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Campos definidos solo en la vista publicada voyager/users/edit-add pueden faltar en el BREAD: Voyager no los
 * escribe en TCG insertUpdateData. Quedando NULL pueden romper restricciones NOT NULL (name, password en alta).
 *
 * La contraseña se asigna en texto plano y el modelo aplica el cast hashed.
 */
final class VoyagerUserBladeFieldsHydration
{
    /**
     * @param  bool  $includePassword  false antes de insertUpdateData cuando el BREAD ya tiene fila password (evita doble vía con ContentTypes\Password).
     * @return bool true si se modificó algún atributo en memoria (posible isDirty).
     */
    public static function hydrateFromPublishedBlade(User $user, Request $request, bool $includePassword = true): bool
    {
        $changed = false;

        if ($request->has('name')) {
            $name = trim((string) $request->input('name', ''));
            if ($user->getAttribute('name') !== $name) {
                $user->name = $name;
                $changed = true;
            }
        }

        if ($request->has('unidad')) {
            $raw = $request->input('unidad');
            $unidad = ($raw === null || $raw === '') ? null : (string) $raw;
            if ($user->getAttribute('unidad') !== $unidad) {
                $user->unidad = $unidad;
                $changed = true;
            }
        }

        if ($request->has('anexo')) {
            $raw = $request->input('anexo');
            $anexo = ($raw === null || $raw === '') ? null : (int) $raw;
            if ($user->getAttribute('anexo') !== $anexo) {
                $user->anexo = $anexo;
                $changed = true;
            }
        }

        if ($includePassword && $request->filled('password')) {
            $user->password = (string) $request->input('password');
            $changed = true;
        }

        return $changed;
    }

    public static function persistIfOutOfSync(User $user, Request $request): void
    {
        self::hydrateFromPublishedBlade($user, $request);
        if ($user->isDirty()) {
            $user->save();
        }
    }
}
