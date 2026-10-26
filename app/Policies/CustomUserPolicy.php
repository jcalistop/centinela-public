<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Voyager\VoyagerUserActorAuthorization;
use TCG\Voyager\Contracts\User as VoyagerUser;
use TCG\Voyager\Policies\UserPolicy;

class CustomUserPolicy extends UserPolicy
{
    /**
     * @param  User  $model
     */
    public function read(VoyagerUser $user, $model): bool
    {
        if (! $user instanceof User || ! $model instanceof User) {
            return parent::read($user, $model);
        }

        if ((int) $user->getKey() === (int) $model->getKey()) {
            return true;
        }

        if ($user->hasRole('admin') && $user->hasPermission('read_users')) {
            return true;
        }

        if (
            $user->hasPermission('read_users')
            && $user->cen_hie_dependency_id !== null
            && (int) $user->cen_hie_dependency_id === (int) $model->cen_hie_dependency_id
        ) {
            return true;
        }

        if (
            $user->hasRole('visualizador')
            && $model->hasRole('referente')
            && $user->cen_hie_dependency_id !== null
            && (int) $user->cen_hie_dependency_id === (int) $model->cen_hie_dependency_id
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param  User  $model
     */
    public function editRoles(VoyagerUser $user, $model): bool
    {
        if (! $user instanceof User || ! $model instanceof User) {
            return false;
        }

        if ($model->exists && (int) $user->getKey() === (int) $model->getKey()) {
            return false;
        }

        if (! $model->exists) {
            return $user->hasPermission('add_users')
                && VoyagerUserActorAuthorization::canManageUser($user, $model);
        }

        return $user->hasPermission('edit_users')
            && VoyagerUserActorAuthorization::canManageUser($user, $model);
    }

    /**
     * @param  User  $model
     */
    public function edit(VoyagerUser $user, $model): bool
    {
        if (! $user instanceof User || ! $model instanceof User) {
            return parent::edit($user, $model);
        }

        if ((int) $user->getKey() === (int) $model->getKey()) {
            return true;
        }

        return $user->hasPermission('edit_users')
            && VoyagerUserActorAuthorization::canManageUser($user, $model);
    }

    /**
     * @param  User  $model
     */
    public function delete(VoyagerUser $user, $model): bool
    {
        if (! $user instanceof User || ! $model instanceof User) {
            return parent::delete($user, $model);
        }

        if ((int) $user->getKey() === (int) $model->getKey()) {
            return false;
        }

        return $user->hasPermission('delete_users')
            && VoyagerUserActorAuthorization::canManageUser($user, $model);
    }
}
