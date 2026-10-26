<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeployAdminOpsTerminalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $executionId,
        public string $mode,
        public string $status,
        public string $summary,
        public string $panelUrl,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[Centinela] Deploy #%d — %s (%s)',
                $this->executionId,
                $this->status,
                $this->mode
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.centinela.deploy-admin-ops-terminal',
        );
    }
}
