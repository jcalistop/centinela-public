<?php

namespace App\Mail;

use App\Models\CenHieDependency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessRequestSubmitted extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{nombre: string, email: string, telefono?: string|null, cargo?: string|null, mensaje?: string|null, cen_hie_dependency_id: int, servicio_salud_nombre?: string|null}  $payload
     */
    public function __construct(
        public array $payload,
        public ?CenHieDependency $servicioSalud,
        public string $servicioSaludDisplay,
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
            subject: '[Centinela] Solicitud de acceso a la plataforma',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.centinela.access-request-submitted',
            with: [
                'payload' => $this->payload,
                'servicioSalud' => $this->servicioSalud,
                'servicioSaludDisplay' => $this->servicioSaludDisplay,
            ],
        );
    }
}
