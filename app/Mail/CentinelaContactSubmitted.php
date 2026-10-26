<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CentinelaContactSubmitted extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{nombre: string, servicio_salud: string, email: string, telefono?: string|null, mensaje: string}  $payload
     */
    public function __construct(
        public array $payload,
        public ?User $submitter = null,
    ) {}

    public function envelope(): Envelope
    {
        $fromEmail = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            replyTo: [
                new Address($this->payload['email'], $this->payload['nombre']),
            ],
            subject: '[Centinela] Solicitud de contacto — ¿Qué es Centinela?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.centinela.contact-submitted',
            with: [
                'payload' => $this->payload,
                'submitter' => $this->submitter,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
