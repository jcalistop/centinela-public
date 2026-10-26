<?php

namespace App\Policies;

use App\Models\AdpPositionAssignment;
use App\Models\User;

class AdpPositionAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->browse($user, null);
    }

    public function browse(User $user, ?AdpPositionAssignment $model = null): bool
    {
        return $user->role?->name === 'admin' || $user->hasPermission('browse_adp_position_assignments');
    }

    public function read(User $user, AdpPositionAssignment $assignment): bool
    {
        return $this->allowsForAssignment($user, $assignment, 'read_adp_position_assignments');
    }

    public function edit(User $user, AdpPositionAssignment $assignment): bool
    {
        return $this->allowsForAssignment($user, $assignment, 'edit_adp_position_assignments');
    }

    public function add(User $user, ?AdpPositionAssignment $model = null): bool
    {
        return $user->role?->name === 'admin' || $user->hasPermission('add_adp_position_assignments');
    }

    public function delete(User $user, AdpPositionAssignment $assignment): bool
    {
        return $this->allowsForAssignment($user, $assignment, 'delete_adp_position_assignments');
    }

    private function allowsForAssignment(User $user, AdpPositionAssignment $assignment, string $permission): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        if (! $user->hasPermission($permission)) {
            return false;
        }

        return (int) $assignment->cen_hie_dependency_id === (int) $user->cen_hie_dependency_id;
    }
}
