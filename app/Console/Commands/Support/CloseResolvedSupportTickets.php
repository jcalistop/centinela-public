<?php

namespace App\Console\Commands\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use Illuminate\Console\Command;

class CloseResolvedSupportTickets extends Command
{
    protected $signature = 'centinela:support-close-resolved';

    protected $description = 'Cierra tickets en estado resuelto tras N días sin mensaje nuevo del solicitante';

    public function handle(): int
    {
        $days = max(1, (int) config('centinela.support.close_resolved_days', 5));
        $cutoff = now()->subDays($days);

        $query = SupportTicket::query()
            ->where('status', SupportTicketStatus::Resolved->value)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $cutoff)
            ->orderBy('id');

        foreach ($query->cursor() as $ticket) {
            $resolvedAt = $ticket->resolved_at;
            if ($resolvedAt === null) {
                continue;
            }

            $hasCreatorFollowUp = $ticket->messages()
                ->where('user_id', $ticket->created_by_user_id)
                ->where('support_ticket_messages.created_at', '>', $resolvedAt)
                ->exists();

            if ($hasCreatorFollowUp) {
                continue;
            }

            $ticket->forceFill(['status' => SupportTicketStatus::Closed])->save();
        }

        return self::SUCCESS;
    }
}
