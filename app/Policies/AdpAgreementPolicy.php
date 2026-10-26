<?php

namespace App\Policies;

use App\Models\AdpAgreement;
use App\Models\User;
use App\Support\Adp\AdpAgreementAccess;

class AdpAgreementPolicy
{
    public function viewAny(User $user): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function browse(User $user, ?AdpAgreement $model = null): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function read(User $user, AdpAgreement $agreement): bool
    {
        return AdpAgreementAccess::read($user, (int) $agreement->cen_hie_dependency_id);
    }

    public function edit(User $user, AdpAgreement $agreement): bool
    {
        return AdpAgreementAccess::edit($user, (int) $agreement->cen_hie_dependency_id);
    }

    public function add(User $user, ?AdpAgreement $model = null): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->hasPermission('add_adp_agreements');
    }

    public function delete(User $user, AdpAgreement $agreement): bool
    {
        return AdpAgreementAccess::delete($user, (int) $agreement->cen_hie_dependency_id);
    }
}
