<?php

namespace App\Policies;

use App\Enums\Indicators\IndicatorActivityAction;
use App\Models\IndicatorActivity;
use App\Models\User;

class IndicatorActivityPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'control-gestion']);
    }

    public function update(User $user, IndicatorActivity $activity): bool
    {
        if ($activity->action !== IndicatorActivityAction::ManualNote) {
            return false;
        }

        return $user->hasRole(['admin', 'control-gestion']);
    }

    public function delete(User $user, IndicatorActivity $activity): bool
    {
        if ($activity->action !== IndicatorActivityAction::ManualNote) {
            return false;
        }

        return $user->hasRole('admin');
    }
}
