<?php

namespace App\Policies;

use App\Enums\Cen\CenCenterOrigen;
use App\Models\CenCenter;
use App\Models\User;
use App\Services\Cen\CenCenterBrowseService;

/**
 * Permisos Voyager del BREAD establecimientos (tabla cen_centers).
 * Filament usa viewAny/create/update/view/delete; se mapean a browse/add/edit/read/delete.
 */
class CenCenterPolicy
{
    public const PERMISSION_TABLE = 'cen_centers';

    public function viewAny(User $user): bool
    {
        return $this->hasVoyagerPermission($user, 'browse');
    }

    public function view(User $user, CenCenter $cenCenter): bool
    {
        return $this->hasVoyagerPermission($user, 'read')
            && $this->withinReadableCatalog($user, $cenCenter);
    }

    public function create(User $user): bool
    {
        return $this->hasVoyagerPermission($user, 'add');
    }

    public function update(User $user, CenCenter $cenCenter): bool
    {
        if (! $this->hasVoyagerPermission($user, 'edit') || ! $this->withinTenancy($user, $cenCenter)) {
            return false;
        }

        return $cenCenter->origen !== CenCenterOrigen::Api;
    }

    public function delete(User $user, CenCenter $cenCenter): bool
    {
        return $this->hasVoyagerPermission($user, 'delete')
            && $this->withinTenancy($user, $cenCenter)
            && $cenCenter->origen !== CenCenterOrigen::Api;
    }

    public function restore(User $user, CenCenter $cenCenter): bool
    {
        return false;
    }

    public function forceDelete(User $user, CenCenter $cenCenter): bool
    {
        return false;
    }

    public function browse(User $user, ?CenCenter $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function add(User $user, ?CenCenter $model = null): bool
    {
        return $this->create($user);
    }

    public function edit(User $user, CenCenter $cenCenter): bool
    {
        return $this->update($user, $cenCenter);
    }

    public function read(User $user, CenCenter $cenCenter): bool
    {
        return $this->view($user, $cenCenter);
    }

    private function hasVoyagerPermission(User $user, string $action): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermission($action.'_'.self::PERMISSION_TABLE);
    }

    private function withinReadableCatalog(User $user, CenCenter $cenCenter): bool
    {
        if ($user->hasRole('admin') && $user->cen_hie_dependency_id === null) {
            return true;
        }

        $centerSs = (int) $cenCenter->cen_hie_dependency_id;
        $own = $user->cen_hie_dependency_id;

        if ($own !== null && $centerSs === (int) $own) {
            return true;
        }

        $browseSs = app(CenCenterBrowseService::class)->effectiveBrowseSsId($user);

        return $browseSs !== null && $centerSs === $browseSs;
    }

    private function withinTenancy(User $user, CenCenter $cenCenter): bool
    {
        if ($user->hasRole('admin') && $user->cen_hie_dependency_id === null) {
            return true;
        }

        return app(CenCenterBrowseService::class)
            ->scopedCentersQueryForUser($user, 'hieFilter')
            ->whereKey($cenCenter->getKey())
            ->exists();
    }
}
