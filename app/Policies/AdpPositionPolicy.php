<?php

namespace App\Policies;

use App\Models\AdpPosition;
use App\Models\User;

class AdpPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->browse($user, null);
    }

    public function browse(User $user, ?AdpPosition $model = null): bool
    {
        return $user->role?->name === 'admin' || $user->hasPermission('browse_adp_positions');
    }

    public function read(User $user, AdpPosition $adpPosition): bool
    {
        return $user->role?->name === 'admin' || $user->hasPermission('read_adp_positions');
    }

    public function edit(User $user, AdpPosition $adpPosition): bool
    {
        return $user->role?->name === 'admin' || $user->hasPermission('edit_adp_positions');
    }

    public function add(User $user, ?AdpPosition $model = null): bool
    {
        return $user->role?->name === 'admin' || $user->hasPermission('add_adp_positions');
    }

    public function delete(User $user, AdpPosition $adpPosition): bool
    {
        return $user->role?->name === 'admin' || $user->hasPermission('delete_adp_positions');
    }
}
