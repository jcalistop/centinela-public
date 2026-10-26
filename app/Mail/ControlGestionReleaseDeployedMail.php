<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Releases\ReleaseNotesEmailHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ControlGestionReleaseDeployedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $bodyHtml;

    public function __construct(
        public string $version,
        public string $releasesUrl,
        string $bodyMarkdown,
        public bool $isTest,
        public string $recipientName,
    ) {
        $this->bodyHtml = ReleaseNotesEmailHtml::render($bodyMarkdown);
    }

    public function envelope(): Envelope
    {
        $subject = '[Centinela] Nueva versión desplegada: '.$this->version;
        if ($this->isTest) {
            $subject = '[PRUEBA] '.$subject;
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.centinela.control-gestion-release-deployed',
        );
    }
}
