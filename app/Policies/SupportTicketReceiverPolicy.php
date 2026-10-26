<?php

namespace App\Policies;

use App\Models\SupportTicketReceiver;
use App\Models\User;

/**
 * CRUD de receptores (mismo criterio que {@see SupportTicketPolicy::manageReceivers()}).
 */
class SupportTicketReceiverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, SupportTicketReceiver $supportTicketReceiver): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, SupportTicketReceiver $supportTicketReceiver): bool
    {
        return $user->hasRole('admin');
    }
}
