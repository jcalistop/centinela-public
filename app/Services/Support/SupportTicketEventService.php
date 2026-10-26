<?php

namespace App\Services\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\User;

class SupportTicketEventService
{
    public function recordStatusChange(
        SupportTicket $ticket,
        User $actor,
        SupportTicketStatus $from,
        SupportTicketStatus $to,
        ?string $comment,
        bool $isInternal
    ): SupportTicketEvent {
        return SupportTicketEvent::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $actor->id,
            'type' => SupportTicketEvent::TYPE_STATUS_CHANGED,
            'is_internal' => $isInternal,
            'comment' => $comment !== '' ? $comment : null,
            'meta' => [
                'from' => $from->value,
                'to' => $to->value,
            ],
        ]);
    }

    public function recordAssignmentChange(
        SupportTicket $ticket,
        User $actor,
        ?User $from,
        ?User $to,
        ?string $comment,
        bool $isInternal
    ): SupportTicketEvent {
        return SupportTicketEvent::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $actor->id,
            'type' => SupportTicketEvent::TYPE_ASSIGNMENT_CHANGED,
            'is_internal' => $isInternal,
            'comment' => $comment !== '' ? $comment : null,
            'meta' => [
                'from_user_id' => $from?->id,
                'to_user_id' => $to?->id,
            ],
        ]);
    }

    public function recordGithubLinked(
        SupportTicket $ticket,
        User $actor,
        int $issueNumber,
        string $issueUrl,
        ?string $comment,
        bool $isInternal
    ): SupportTicketEvent {
        return SupportTicketEvent::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $actor->id,
            'type' => SupportTicketEvent::TYPE_GITHUB_LINKED,
            'is_internal' => $isInternal,
            'comment' => $comment !== '' ? $comment : null,
            'meta' => [
                'issue_number' => $issueNumber,
                'issue_url' => $issueUrl,
            ],
        ]);
    }
}
