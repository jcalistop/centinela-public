<?php

namespace App\Support\Voyager;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autorización del BREAD Voyager users (SDD-085).
 */
final class VoyagerUserActorAuthorization
{
    /** @var list<string> */
    public const PROTECTED_ROLE_NAMES = ['admin', 'parametrizador'];

    public static function isProtectedRole(Role|User|int|string|null $role): bool
    {
        $name = self::resolveRoleName($role);

        return $name !== null && in_array($name, self::PROTECTED_ROLE_NAMES, true);
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        if (! $actor->hasRole('control-gestion')) {
            return false;
        }

        if ($target->exists && self::isProtectedRole($target)) {
            return false;
        }

        $actorSs = $actor->cen_hie_dependency_id;
        if ($actorSs === null) {
            return false;
        }

        $targetSs = self::resolveTargetCenHieDependencyId($target);
        if ($targetSs === null) {
            return false;
        }

        return (int) $actorSs === (int) $targetSs;
    }

    public static function canAssignRole(User $actor, Role|int|string|null $role): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        if (! $actor->hasRole('control-gestion')) {
            return false;
        }

        return ! self::isProtectedRole($role);
    }

    public static function assertCanManageUser(User $actor, User $target): void
    {
        if (! self::canManageUser($actor, $target)) {
            throw new AuthorizationException('No tiene permiso para gestionar este usuario.');
        }
    }

    public static function assertCanAssignRole(User $actor, Role|int|string|null $role): void
    {
        if (! self::canAssignRole($actor, $role)) {
            throw new AuthorizationException('No tiene permiso para asignar este rol.');
        }
    }

    /**
     * Rol resuelto desde request (role_id o belongsTo virtual).
     */
    public static function resolveRoleFromRequest(Request $request): Role|int|string|null
    {
        if ($request->filled('role_id')) {
            return $request->input('role_id');
        }

        if ($request->filled('user_belongsto_role_relationship')) {
            return $request->input('user_belongsto_role_relationship');
        }

        return null;
    }

    private static function resolveRoleName(Role|User|int|string|null $role): ?string
    {
        if ($role === null) {
            return null;
        }

        if ($role instanceof User) {
            $role->loadMissing('role');

            return $role->role?->name;
        }

        if ($role instanceof Role) {
            return $role->name;
        }

        if (is_string($role) && ! is_numeric($role)) {
            return $role;
        }

        $roleId = is_numeric($role) ? (int) $role : null;
        if ($roleId === null || $roleId <= 0) {
            return null;
        }

        return Role::query()->whereKey($roleId)->value('name');
    }

    public static function authenticatedActor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * SS efectivo del usuario objetivo. La columna es NOT NULL; en alta sin persistir
     * aún, control-gestión siempre asigna el SS del actor ({@see VoyagerUserControlGestionCenHieDependency}).
     */
    private static function resolveTargetCenHieDependencyId(User $target): ?int
    {
        if ($target->cen_hie_dependency_id !== null) {
            return $target->cen_hie_dependency_id;
        }

        if (! $target->exists) {
            $actor = self::authenticatedActor();

            return $actor instanceof User ? $actor->cen_hie_dependency_id : null;
        }

        return null;
    }
}
