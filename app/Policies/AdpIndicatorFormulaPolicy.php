<?php

namespace App\Policies;

use App\Models\AdpAgreement;
use App\Models\AdpIndicatorFormula;
use App\Models\User;
use App\Support\Adp\AdpAgreementAccess;

class AdpIndicatorFormulaPolicy
{
    public function viewAny(User $user): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function read(User $user, AdpIndicatorFormula $formula): bool
    {
        $hie = self::authorizationHieId($formula);

        return AdpAgreementAccess::read($user, $hie);
    }

    public function edit(User $user, AdpIndicatorFormula $formula): bool
    {
        $hie = self::authorizationHieId($formula);

        return AdpAgreementAccess::edit($user, $hie);
    }

    public function add(User $user, ?AdpIndicatorFormula $model = null): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->hasPermission('add_adp_agreements');
    }

    public function delete(User $user, AdpIndicatorFormula $formula): bool
    {
        $hie = self::authorizationHieId($formula);

        return AdpAgreementAccess::delete($user, $hie);
    }

    private static function authorizationHieId(AdpIndicatorFormula $formula): int
    {
        $period = $formula->indicator->objective->period;
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return (int) $agreement->cen_hie_dependency_id;
    }
}
