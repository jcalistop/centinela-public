<?php

namespace App\Policies;

use App\Models\IndicatorTypeSetting;
use App\Models\User;

/**
 * Permisos Voyager del BREAD configurar-tipo-indicador (tabla indicator_type_settings).
 * Filament usa viewAny/create/update/view/delete; se mapean a browse/add/edit/read/delete.
 */
class IndicatorTypeSettingPolicy
{
    public const PERMISSION_TABLE = 'indicator_type_settings';

    public function viewAny(User $user): bool
    {
        return $this->hasVoyagerPermission($user, 'browse');
    }

    public function view(User $user, IndicatorTypeSetting $indicatorTypeSetting): bool
    {
        return $this->hasVoyagerPermission($user, 'read');
    }

    public function create(User $user): bool
    {
        return $this->hasVoyagerPermission($user, 'add');
    }

    public function update(User $user, IndicatorTypeSetting $indicatorTypeSetting): bool
    {
        return $this->hasVoyagerPermission($user, 'edit');
    }

    public function delete(User $user, IndicatorTypeSetting $indicatorTypeSetting): bool
    {
        return $this->hasVoyagerPermission($user, 'delete');
    }

    public function restore(User $user, IndicatorTypeSetting $indicatorTypeSetting): bool
    {
        return false;
    }

    public function forceDelete(User $user, IndicatorTypeSetting $indicatorTypeSetting): bool
    {
        return false;
    }

    /**
     * Alias Voyager (autorización BREAD en controlador).
     */
    public function browse(User $user, ?IndicatorTypeSetting $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function add(User $user, ?IndicatorTypeSetting $model = null): bool
    {
        return $this->create($user);
    }

    public function edit(User $user, IndicatorTypeSetting $indicatorTypeSetting): bool
    {
        return $this->update($user, $indicatorTypeSetting);
    }

    public function read(User $user, IndicatorTypeSetting $indicatorTypeSetting): bool
    {
        return $this->view($user, $indicatorTypeSetting);
    }

    private function hasVoyagerPermission(User $user, string $action): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermission($action.'_'.self::PERMISSION_TABLE);
    }
}
