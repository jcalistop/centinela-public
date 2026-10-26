<?php

namespace App\Policies;

use App\Models\IndicatorType;
use App\Models\User;

/**
 * Permisos Voyager del BREAD instrumentos-evaluacion (tabla indicator_types).
 * Filament usa viewAny/create/update/view/delete; se mapean a browse/add/edit/read/delete.
 */
class IndicatorTypePolicy
{
    public const PERMISSION_TABLE = 'indicator_types';

    public function viewAny(User $user): bool
    {
        return $this->hasVoyagerPermission($user, 'browse');
    }

    public function view(User $user, IndicatorType $indicatorType): bool
    {
        return $this->hasVoyagerPermission($user, 'read');
    }

    public function create(User $user): bool
    {
        return $this->hasVoyagerPermission($user, 'add');
    }

    public function update(User $user, IndicatorType $indicatorType): bool
    {
        return $this->hasVoyagerPermission($user, 'edit');
    }

    public function delete(User $user, IndicatorType $indicatorType): bool
    {
        return $this->hasVoyagerPermission($user, 'delete');
    }

    public function restore(User $user, IndicatorType $indicatorType): bool
    {
        return false;
    }

    public function forceDelete(User $user, IndicatorType $indicatorType): bool
    {
        return false;
    }

    /**
     * Alias Voyager (autorización BREAD en controlador).
     */
    public function browse(User $user, ?IndicatorType $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function add(User $user, ?IndicatorType $model = null): bool
    {
        return $this->create($user);
    }

    public function edit(User $user, IndicatorType $indicatorType): bool
    {
        return $this->update($user, $indicatorType);
    }

    public function read(User $user, IndicatorType $indicatorType): bool
    {
        return $this->view($user, $indicatorType);
    }

    private function hasVoyagerPermission(User $user, string $action): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermission($action.'_'.self::PERMISSION_TABLE);
    }
}
