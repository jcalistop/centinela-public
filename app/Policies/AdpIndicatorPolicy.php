<?php

namespace App\Policies;

use App\Models\AdpAgreement;
use App\Models\AdpIndicator;
use App\Models\User;
use App\Support\Adp\AdpAgreementAccess;

class AdpIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function read(User $user, AdpIndicator $indicator): bool
    {
        $hie = self::authorizationHieId($indicator);

        return AdpAgreementAccess::read($user, $hie);
    }

    public function edit(User $user, AdpIndicator $indicator): bool
    {
        $hie = self::authorizationHieId($indicator);

        return AdpAgreementAccess::edit($user, $hie);
    }

    public function add(User $user, ?AdpIndicator $model = null): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->hasPermission('add_adp_agreements');
    }

    public function delete(User $user, AdpIndicator $indicator): bool
    {
        $hie = self::authorizationHieId($indicator);

        return AdpAgreementAccess::delete($user, $hie);
    }

    private static function authorizationHieId(AdpIndicator $indicator): int
    {
        $period = $indicator->objective->period;
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return (int) $agreement->cen_hie_dependency_id;
    }
}
