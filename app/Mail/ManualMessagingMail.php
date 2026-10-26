<?php

namespace App\Mail;

use App\Models\MessagingCampaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManualMessagingMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public MessagingCampaign $campaign,
        public User $sender,
    ) {}

    public function envelope(): Envelope
    {
        $fromEmail = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            replyTo: [
                new Address((string) $this->sender->email, (string) $this->sender->name),
            ],
            subject: $this->campaign->subject,
        );
    }

    public function content(): Content
    {
        $sender = $this->sender;
        $payload = [
            'subject' => $this->campaign->subject,
            'bodyHtml' => $this->campaign->body_html,
            'bodyText' => $this->campaign->body_text,
            'senderName' => (string) $sender->name,
            'senderEmail' => (string) $sender->email,
            'senderUnidad' => (string) ($sender->unidad ?: '—'),
            'senderServicioSalud' => (string) ($sender->cenHieDependency?->nombre ?? '—'),
        ];

        return new Content(
            view: 'emails.manual-messaging',
            text: 'emails.manual-messaging-text',
            with: $payload,
        );
    }
}
