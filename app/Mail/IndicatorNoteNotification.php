<?php

namespace App\Mail;

use App\Models\IndicatorActivity;
use App\Models\User;
use App\Services\Notifications\IndicatorNoteMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IndicatorNoteNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public IndicatorActivity $activity,
        public IndicatorNoteMailData $mailData,
        public string $appName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Centinela] Nueva nota en bitácora de indicador',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.indicator-note',
            text: 'emails.indicator-note-text',
            with: [
                'user' => $this->user,
                'activity' => $this->activity,
                'mailData' => $this->mailData,
                'appName' => $this->appName,
            ],
        );
    }
}
