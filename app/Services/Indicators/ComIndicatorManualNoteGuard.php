<?php

namespace App\Services\Indicators;

use App\Helpers\Helper;
use App\Models\ComIndicator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ComIndicatorManualNoteGuard
{
    public function canAddNote(ComIndicator $indicator, ?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->hasRole(['admin', 'control-gestion'])) {
            return false;
        }

        if (! $this->instrumentEnabledForUser($indicator, $user)) {
            return false;
        }

        return true;
    }

    private function instrumentEnabledForUser(ComIndicator $indicator, User $user): bool
    {
        $indicatorTypeSetting = $indicator->comCommitment
            ->indicatorType
            ->indicatorTypeSettings
            ->where('year_id', Helper::showCurrentYear()->id)
            ->where('cen_hie_dependency_id', $user->cen_hie_dependency_id)
            ->first();

        return $indicatorTypeSetting !== null && (bool) $indicatorTypeSetting->habilitado;
    }
}
