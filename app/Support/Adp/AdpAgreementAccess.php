<?php

namespace App\Support\Adp;

use App\Models\User;

final class AdpAgreementAccess
{
    public static function browse(User $user): bool
    {
        return $user->role?->name === 'admin' || $user->hasPermission('browse_adp_agreements');
    }

    public static function read(User $user, int $cenHieDependencyId): bool
    {
        return self::allows($user, $cenHieDependencyId, 'read_adp_agreements');
    }

    public static function edit(User $user, int $cenHieDependencyId): bool
    {
        return self::allows($user, $cenHieDependencyId, 'edit_adp_agreements');
    }

    public static function add(User $user, int $cenHieDependencyId): bool
    {
        return self::allows($user, $cenHieDependencyId, 'add_adp_agreements');
    }

    public static function delete(User $user, int $cenHieDependencyId): bool
    {
        return self::allows($user, $cenHieDependencyId, 'delete_adp_agreements');
    }

    private static function allows(User $user, int $cenHieDependencyId, string $permission): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        if (! $user->hasPermission($permission)) {
            return false;
        }

        return (int) $cenHieDependencyId === (int) $user->cen_hie_dependency_id;
    }
}
