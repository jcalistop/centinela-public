<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Notifications\ReferenteAssignmentMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferenteAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ReferenteAssignmentMailData $mailData,
        public string $appName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Centinela] Asignación como referente de indicador',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.referente-assigned',
            text: 'emails.referente-assigned-text',
            with: [
                'user' => $this->user,
                'mailData' => $this->mailData,
                'appName' => $this->appName,
            ],
        );
    }
}
