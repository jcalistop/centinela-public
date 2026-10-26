<?php

namespace App\Policies;

use App\Models\IndicatorAmbito;
use App\Models\User;

/**
 * Permisos Voyager del BREAD Ámbitos de indicadores (tabla indicator_ambitos).
 */
class IndicatorAmbitoPolicy
{
    public const PERMISSION_TABLE = 'indicator_ambitos';

    public function browse(User $user, ?IndicatorAmbito $model = null): bool
    {
        return $this->hasVoyagerPermission($user, 'browse');
    }

    public function read(User $user, IndicatorAmbito $indicatorAmbito): bool
    {
        return $this->hasVoyagerPermission($user, 'read');
    }

    public function edit(User $user, IndicatorAmbito $indicatorAmbito): bool
    {
        return $this->hasVoyagerPermission($user, 'edit');
    }

    public function add(User $user, ?IndicatorAmbito $model = null): bool
    {
        return $this->hasVoyagerPermission($user, 'add');
    }

    public function delete(User $user, IndicatorAmbito $indicatorAmbito): bool
    {
        return $this->hasVoyagerPermission($user, 'delete');
    }

    private function hasVoyagerPermission(User $user, string $action): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermission($action.'_'.self::PERMISSION_TABLE);
    }
}
