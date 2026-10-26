<?php

namespace App\Policies;

use App\Models\AdpAgreement;
use App\Models\AdpIndicatorTarget;
use App\Models\User;
use App\Support\Adp\AdpAgreementAccess;

class AdpIndicatorTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function read(User $user, AdpIndicatorTarget $target): bool
    {
        $hie = self::authorizationHieId($target);

        return AdpAgreementAccess::read($user, $hie);
    }

    public function edit(User $user, AdpIndicatorTarget $target): bool
    {
        $hie = self::authorizationHieId($target);

        return AdpAgreementAccess::edit($user, $hie);
    }

    public function add(User $user, ?AdpIndicatorTarget $model = null): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->hasPermission('add_adp_agreements');
    }

    public function delete(User $user, AdpIndicatorTarget $target): bool
    {
        $hie = self::authorizationHieId($target);

        return AdpAgreementAccess::delete($user, $hie);
    }

    private static function authorizationHieId(AdpIndicatorTarget $target): int
    {
        $period = $target->indicator->objective->period;
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return (int) $agreement->cen_hie_dependency_id;
    }
}
