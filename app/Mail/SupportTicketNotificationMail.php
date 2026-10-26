<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const KIND_CREATED = 'created';

    public const KIND_MESSAGE = 'message';

    public const KIND_STATUS = 'status';

    public const KIND_ASSIGNMENT = 'assignment';

    public function __construct(
        public SupportTicket $ticket,
        public string $ticketUrl,
        public string $emailSubjectLine,
        public string $kind = self::KIND_CREATED,
        public ?string $comment = null,
        public ?string $commentAuthorName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Centinela] '.$this->emailSubjectLine,
        );
    }

    public function content(): Content
    {
        $this->ticket->loadMissing([
            'createdBy',
            'assignedTo',
            'cenHieDependency',
            'indicatorType',
            'year',
        ]);

        $actionIcon = '';
        $actionText = '';

        if ($this->kind === self::KIND_MESSAGE) {
            $actionIcon = '&#9993;'; // ✉
            $actionText = 'Se ha creado un nuevo mensaje en ticket de soporte.';
        } elseif ($this->kind === self::KIND_STATUS) {
            $actionIcon = '&#128260;'; // 🔄
            $actionText = 'El estado del ticket ha sido actualizado.';
        } elseif ($this->kind === self::KIND_ASSIGNMENT) {
            $actionIcon = '&#128101;'; // 👥
            $actionText = 'La asignación del ticket ha sido actualizada.';
        } elseif ($this->kind === self::KIND_CREATED) {
            $actionIcon = '&#128229;'; // 📩
            $actionText = 'Se ha creado un nuevo ticket de soporte.';
        } else {
            $actionIcon = '&#128196;'; // 📄
            $actionText = 'Notificación del módulo Soporte Centinela.';
        }

        return new Content(
            view: 'emails.support-ticket-notification',
            with: [
                'ticket' => $this->ticket,
                'ticketUrl' => $this->ticketUrl,
                'emailSubjectLine' => $this->emailSubjectLine,
                'actionIcon' => $actionIcon,
                'actionText' => $actionText,
                'comment' => $this->comment,
                'commentAuthorName' => $this->commentAuthorName,
            ],
        );
    }
}
