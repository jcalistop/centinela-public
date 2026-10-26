<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Soft multi-tenancy por Servicio de Salud (`cen_hie_dependency_id`).
 *
 * GlobalScope nombrado `belongs_to_health_service`. Filtra a **todo** usuario
 * autenticado (incl. `admin` y `parametrizador`) por su SS asignado — la UI
 * custom aún no está preparada para listados cross-SS.
 * Sin usuario (jobs/console): no filtra — usar
 * `Model::withoutGlobalScope('belongs_to_health_service')` o filtro explícito.
 * ADP conserva su bypass admin propio; no se toca en este trait.
 */
trait BelongsToHealthService
{
    public static function bootBelongsToHealthService(): void
    {
        BelongsToHealthServiceScopeRegistrar::register(static::class);
    }
}

final class BelongsToHealthServiceScopeRegistrar
{
    public static function register(string $modelClass): void
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            return;
        }

        $modelClass::addGlobalScope('belongs_to_health_service', function (Builder $builder): void {
            $user = Auth::user();

            if (! $user instanceof User) {
                return;
            }

            $model = $builder->getModel();
            $column = property_exists($model, 'healthServiceColumn')
                ? $model->healthServiceColumn
                : 'cen_hie_dependency_id';

            $builder->where(
                $model->getTable().'.'.$column,
                $user->cen_hie_dependency_id
            );
        });
    }
}
