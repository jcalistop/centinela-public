<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MetasPerfBaselineReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public array $report) {}

    public function envelope(): Envelope
    {
        $partial = ! empty($this->report['partial']) ? ' (parcial)' : '';

        return new Envelope(
            subject: '[Centinela] Baseline de rendimiento Metas'.$partial.' — '.now()->format('d/m/Y H:i'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.metas-perf-baseline-report',
            with: ['report' => $this->report],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = $this->report['markdown_path'] ?? null;
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return [];
        }

        if (filesize($path) > 512_000) {
            return [];
        }

        return [
            Attachment::fromPath($path)->as(basename($path))->withMime('text/markdown'),
        ];
    }
}
