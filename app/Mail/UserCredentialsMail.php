<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserCredentialsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $plainPassword,
        public string $loginUrl,
        public string $appName,
        public bool $isUpdate = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Centinela] Credenciales de acceso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-credentials',
            text: 'emails.user-credentials-text',
            with: [
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'loginUrl' => $this->loginUrl,
                'appName' => $this->appName,
                'isUpdate' => $this->isUpdate,
            ],
        );
    }
}
