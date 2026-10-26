<?php

namespace App\Services\Support;

use App\Enums\SupportTicketArea;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SupportTicketAssignmentService
{
    /**
     * @return Collection<int, User>
     */
    public function assignableUsers(SupportTicket $ticket, User $assigner): Collection
    {
        return $this->assignableUsersQuery($ticket, $assigner)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Builder<User>
     */
    public function assignableUsersQuery(SupportTicket $ticket, User $assigner): Builder
    {
        $ss = (int) $ticket->cen_hie_dependency_id;

        return User::query()
            ->where(function (Builder $w) use ($ticket, $assigner, $ss): void {
                if ($assigner->hasRole('admin')) {
                    $w->whereHas('role', fn (Builder $r) => $r->where('name', 'admin'))
                        ->orWhere(function (Builder $w2) use ($ss): void {
                            $w2->where('cen_hie_dependency_id', $ss)
                                ->whereHas('role', fn (Builder $r) => $r->where('name', 'control-gestion'));
                        });

                    return;
                }

                if ($assigner->hasRole('control-gestion')) {
                    $w->whereHas('role', fn (Builder $r) => $r->where('name', 'admin'))
                        ->orWhere(function (Builder $w2) use ($ss): void {
                            $w2->where('cen_hie_dependency_id', $ss)
                                ->whereHas('role', fn (Builder $r) => $r->where('name', 'control-gestion'));
                        });

                    if ($ticket->area === SupportTicketArea::Gestion) {
                        $w->orWhere(function (Builder $w2) use ($ss): void {
                            $w2->where('cen_hie_dependency_id', $ss)
                                ->whereHas('role', fn (Builder $r) => $r->where('name', 'referente'));
                        });
                    }
                }
            })
            ->whereDoesntHave('role', fn (Builder $r) => $r->whereIn('name', [
                'parametrizador',
                'visualizador',
            ]));
    }

    public function targetIsAssignable(SupportTicket $ticket, User $assigner, User $target): bool
    {
        if ($target->role?->name === 'parametrizador' || $target->role?->name === 'visualizador') {
            return false;
        }

        $ss = (int) $ticket->cen_hie_dependency_id;

        if ($assigner->hasRole('admin')) {
            if ($target->hasRole('referente')) {
                return false;
            }
            if ($target->hasRole('admin')) {
                return true;
            }
            if ($target->hasRole('control-gestion')) {
                return (int) ($target->cen_hie_dependency_id ?? 0) === $ss;
            }

            return false;
        }

        if ($assigner->hasRole('control-gestion')) {
            if ((int) ($assigner->cen_hie_dependency_id ?? 0) !== $ss) {
                return false;
            }
            if ($target->hasRole('admin')) {
                return true;
            }
            if ($target->hasRole('control-gestion')) {
                return (int) ($target->cen_hie_dependency_id ?? 0) === $ss;
            }
            if ($target->hasRole('referente')) {
                return (int) ($target->cen_hie_dependency_id ?? 0) === $ss
                    && $ticket->area === SupportTicketArea::Gestion;
            }

            return false;
        }

        return false;
    }
}
