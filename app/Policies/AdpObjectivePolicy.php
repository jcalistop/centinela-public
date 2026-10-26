<?php

namespace App\Policies;

use App\Models\AdpAgreement;
use App\Models\AdpObjective;
use App\Models\User;
use App\Support\Adp\AdpAgreementAccess;

class AdpObjectivePolicy
{
    public function viewAny(User $user): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function read(User $user, AdpObjective $objective): bool
    {
        $hie = self::authorizationHieId($objective);

        return AdpAgreementAccess::read($user, $hie);
    }

    public function edit(User $user, AdpObjective $objective): bool
    {
        $hie = self::authorizationHieId($objective);

        return AdpAgreementAccess::edit($user, $hie);
    }

    public function add(User $user, ?AdpObjective $model = null): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->hasPermission('add_adp_agreements');
    }

    public function delete(User $user, AdpObjective $objective): bool
    {
        $hie = self::authorizationHieId($objective);

        return AdpAgreementAccess::delete($user, $hie);
    }

    private static function authorizationHieId(AdpObjective $objective): int
    {
        $period = $objective->period;
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return (int) $agreement->cen_hie_dependency_id;
    }
}
