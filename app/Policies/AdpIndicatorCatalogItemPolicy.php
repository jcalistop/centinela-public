<?php

namespace App\Policies;

use App\Models\AdpIndicatorCatalogItem;
use App\Models\User;

class AdpIndicatorCatalogItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'control-gestion']);
    }

    public function view(User $user, AdpIndicatorCatalogItem $adpIndicatorCatalogItem): bool
    {
        return $this->sameSsOrAdmin($user, $adpIndicatorCatalogItem);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'control-gestion']);
    }

    public function update(User $user, AdpIndicatorCatalogItem $adpIndicatorCatalogItem): bool
    {
        return $this->sameSsOrAdmin($user, $adpIndicatorCatalogItem)
            && $user->hasRole(['admin', 'control-gestion']);
    }

    public function delete(User $user, AdpIndicatorCatalogItem $adpIndicatorCatalogItem): bool
    {
        return $this->sameSsOrAdmin($user, $adpIndicatorCatalogItem)
            && $user->hasRole(['admin', 'control-gestion']);
    }

    private function sameSsOrAdmin(User $user, AdpIndicatorCatalogItem $item): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return (int) $user->cen_hie_dependency_id === (int) $item->cen_hie_dependency_id;
    }
}
