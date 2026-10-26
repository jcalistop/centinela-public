<?php

namespace App\Support\Voyager;

use App\Models\User;
use Illuminate\Http\Request;
use TCG\Voyager\Http\Controllers\Controller;

/**
 * El formulario publicado oculta Servicio de Salud para control-gestion; la columna es NOT NULL.
 * Voyager no persiste belongsTo en {@see Controller::insertUpdateData},
 * por lo que hay que fijar el FK en el modelo antes del save inicial.
 */
final class VoyagerUserControlGestionCenHieDependency
{
    public static function applyToRequestAndModel(User $user, Request $request): void
    {
        $auth = auth()->user();
        if (! $auth instanceof User || ! $auth->hasRole('control-gestion')) {
            return;
        }

        $dependencyId = $auth->cen_hie_dependency_id;
        if ($dependencyId === null) {
            return;
        }

        $request->merge([
            'cen_hie_dependency_id' => (string) $dependencyId,
        ]);

        $user->cen_hie_dependency_id = $dependencyId;
    }
}
