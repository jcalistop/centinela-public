<?php

namespace App\Services\Support;

use App\Enums\SupportTicketStatus;
use App\Helpers\ModuleAccess;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketRead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class SupportTicketDashboardAlertService
{
    /**
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    public function dashboardAlertsForUser(User $user, int $hieId): array
    {
        if (! $this->isApplicable($user, $hieId)) {
            return [];
        }

        $baseQuery = SupportTicket::query()->visibleToUser($user);
        $alerts = [];

        $unread = $this->countUnreadTickets($user, $baseQuery);
        if ($unread > 0) {
            $alerts[] = $this->alert(
                'warning',
                'Soporte: tickets no leídos',
                $this->pluralize($unread, 'Tienes %d ticket con mensajes nuevos.', 'Tienes %d tickets con mensajes nuevos.'),
                $this->supportIndexUrl(),
            );
        }

        $open = (clone $baseQuery)->where('status', SupportTicketStatus::Open)->count();
        if ($open > 0) {
            $alerts[] = $this->alert(
                'info',
                'Soporte: tickets abiertos',
                $this->pluralize($open, 'Hay %d ticket abierto en tu bandeja.', 'Hay %d tickets abiertos en tu bandeja.'),
                $this->supportIndexUrl(['status' => SupportTicketStatus::Open->value]),
            );
        }

        $inProgress = (clone $baseQuery)->where('status', SupportTicketStatus::InProgress)->count();
        if ($inProgress > 0) {
            $alerts[] = $this->alert(
                'info',
                'Soporte: tickets en curso',
                $this->pluralize($inProgress, 'Hay %d ticket en curso en tu bandeja.', 'Hay %d tickets en curso en tu bandeja.'),
                $this->supportIndexUrl(['status' => SupportTicketStatus::InProgress->value]),
            );
        }

        return $alerts;
    }

    private function isApplicable(User $user, int $hieId): bool
    {
        if (! Schema::hasTable('support_tickets')) {
            return false;
        }

        if (! Route::has('centinela.support.index')) {
            return false;
        }

        if (! Gate::forUser($user)->allows('viewAny', SupportTicket::class)) {
            return false;
        }

        return ModuleAccess::canAccess('soporte', $hieId);
    }

    /**
     * @param  Builder<SupportTicket>  $baseQuery
     */
    private function countUnreadTickets(User $user, Builder $baseQuery): int
    {
        $ticketIds = (clone $baseQuery)->pluck('id');
        if ($ticketIds->isEmpty()) {
            return 0;
        }

        $lastMessages = SupportTicketMessage::query()
            ->whereIn('support_ticket_id', $ticketIds)
            ->selectRaw('support_ticket_id, MAX(created_at) as last_at')
            ->groupBy('support_ticket_id')
            ->pluck('last_at', 'support_ticket_id');

        $reads = SupportTicketRead::query()
            ->where('user_id', $user->id)
            ->whereIn('support_ticket_id', $ticketIds)
            ->pluck('last_read_at', 'support_ticket_id');

        $unread = 0;
        foreach ($ticketIds as $id) {
            $lastRaw = $lastMessages[$id] ?? null;
            if ($lastRaw === null) {
                continue;
            }

            $read = $reads[$id] ?? null;
            if ($read === null) {
                $unread++;

                continue;
            }

            if (Carbon::parse($lastRaw)->isAfter($read)) {
                $unread++;
            }
        }

        return $unread;
    }

    /**
     * @return array{severity: string, title: string, body: string, url: string}
     */
    private function alert(string $severity, string $title, string $body, string $url): array
    {
        return [
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ];
    }

    /**
     * @param  array<string, string>  $params
     */
    private function supportIndexUrl(array $params = []): string
    {
        return route('centinela.support.index', $params);
    }

    private function pluralize(int $count, string $singular, string $plural): string
    {
        return sprintf($count === 1 ? $singular : $plural, $count);
    }
}
