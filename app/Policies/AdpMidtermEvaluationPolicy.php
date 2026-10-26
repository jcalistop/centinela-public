<?php

namespace App\Policies;

use App\Models\AdpAgreement;
use App\Models\AdpMidtermEvaluation;
use App\Models\User;
use App\Support\Adp\AdpAgreementAccess;

class AdpMidtermEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function read(User $user, AdpMidtermEvaluation $row): bool
    {
        $hie = self::authorizationHieId($row);

        return AdpAgreementAccess::read($user, $hie);
    }

    public function edit(User $user, AdpMidtermEvaluation $row): bool
    {
        $hie = self::authorizationHieId($row);

        return AdpAgreementAccess::edit($user, $hie);
    }

    public function add(User $user, ?AdpMidtermEvaluation $model = null): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->hasPermission('add_adp_agreements');
    }

    public function delete(User $user, AdpMidtermEvaluation $row): bool
    {
        $hie = self::authorizationHieId($row);

        return AdpAgreementAccess::delete($user, $hie);
    }

    private static function authorizationHieId(AdpMidtermEvaluation $row): int
    {
        $period = $row->agreementPeriod;
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return (int) $agreement->cen_hie_dependency_id;
    }
}
