<?php

namespace App\Policies;

use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\User;
use App\Support\Adp\AdpAgreementAccess;

class AdpAgreementPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function read(User $user, AdpAgreementPeriod $period): bool
    {
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return AdpAgreementAccess::read($user, (int) $agreement->cen_hie_dependency_id);
    }

    public function edit(User $user, AdpAgreementPeriod $period): bool
    {
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return AdpAgreementAccess::edit($user, (int) $agreement->cen_hie_dependency_id);
    }

    public function add(User $user, ?AdpAgreementPeriod $model = null): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->hasPermission('add_adp_agreements');
    }

    public function delete(User $user, AdpAgreementPeriod $period): bool
    {
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return AdpAgreementAccess::delete($user, (int) $agreement->cen_hie_dependency_id);
    }
}
