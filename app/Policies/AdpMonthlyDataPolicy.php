<?php

namespace App\Policies;

use App\Models\AdpAgreement;
use App\Models\AdpMonthlyData;
use App\Models\User;
use App\Support\Adp\AdpAgreementAccess;

class AdpMonthlyDataPolicy
{
    public function viewAny(User $user): bool
    {
        return AdpAgreementAccess::browse($user);
    }

    public function read(User $user, AdpMonthlyData $row): bool
    {
        $hie = self::authorizationHieId($row);

        return AdpAgreementAccess::read($user, $hie);
    }

    public function edit(User $user, AdpMonthlyData $row): bool
    {
        $hie = self::authorizationHieId($row);

        return AdpAgreementAccess::edit($user, $hie);
    }

    public function add(User $user, ?AdpMonthlyData $model = null): bool
    {
        if ($user->role?->name === 'admin') {
            return true;
        }

        return $user->hasPermission('add_adp_agreements');
    }

    public function delete(User $user, AdpMonthlyData $row): bool
    {
        $hie = self::authorizationHieId($row);

        return AdpAgreementAccess::delete($user, $hie);
    }

    private static function authorizationHieId(AdpMonthlyData $row): int
    {
        $period = $row->agreementPeriod;
        $agreement = AdpAgreement::withoutGlobalScopes()->findOrFail($period->adp_agreement_id);

        return (int) $agreement->cen_hie_dependency_id;
    }
}
