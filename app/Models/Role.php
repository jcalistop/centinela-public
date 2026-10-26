<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Role extends \TCG\Voyager\Models\Role
{
    /**
     * Roles asignables en BREAD usuarios (excluye solo Administrador).
     * Parametrizador (id 5) debe aparecer al crear/editar usuarios.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeNotAdmin(Builder $query): Builder
    {
        return $query->where('id', '!=', 1);
    }
}
