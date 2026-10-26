<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    /**
     * Roles sin acceso al módulo Soporte (misma categoría de exclusión).
     */
    private function isExcludedFromSupportModule(User $user): bool
    {
        return in_array($user->role?->name, ['parametrizador'], true);
    }

    public function viewAny(User $user): bool
    {
        return ! $this->isExcludedFromSupportModule($user);
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAccessTicket($user, $supportTicket);
    }

    public function create(User $user): bool
    {
        return ! $this->isExcludedFromSupportModule($user);
    }

    public function update(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAccessTicket($user, $supportTicket);
    }

    /**
     * Responder / publicar mensajes en el hilo.
     */
    public function reply(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAccessTicket($user, $supportTicket);
    }

    /**
     * Cambiar estado o asignado (no referente salvo casos triviales; referente solo responde).
     */
    public function manage(User $user, SupportTicket $supportTicket): bool
    {
        if ($this->isExcludedFromSupportModule($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('referente')) {
            return false;
        }

        return $this->sameServicioSalud($user, $supportTicket)
            && $user->hasRole('control-gestion');
    }

    /**
     * Asignar a otro usuario (admin o control-gestión según reglas SDD-048).
     */
    public function assign(User $user, SupportTicket $supportTicket): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('referente')) {
            return false;
        }

        return $this->sameServicioSalud($user, $supportTicket)
            && $user->hasRole('control-gestion');
    }

    /**
     * Solo control-gestión puede asignar a referente (validación de destino en FormRequest).
     */
    public function assignReferente(User $user, SupportTicket $supportTicket): bool
    {
        return $this->sameServicioSalud($user, $supportTicket)
            && $user->hasRole('control-gestion');
    }

    public function createGithubIssue(User $user): bool
    {
        return $user->hasRole('admin')
            && config('centinela.github_issues.enabled');
    }

    public function manageReceivers(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function sameServicioSalud(User $user, SupportTicket $ticket): bool
    {
        return (int) $user->cen_hie_dependency_id === (int) $ticket->cen_hie_dependency_id;
    }

    private function canAccessTicket(User $user, SupportTicket $ticket): bool
    {
        if ($this->isExcludedFromSupportModule($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $this->sameServicioSalud($user, $ticket)) {
            return false;
        }

        if ($user->hasRole('visualizador')) {
            return (int) $ticket->created_by_user_id === (int) $user->id;
        }

        if ($user->hasRole('referente')) {
            return $ticket->created_by_user_id === $user->id
                || $ticket->assigned_to_user_id === $user->id;
        }

        return true;
    }
}
