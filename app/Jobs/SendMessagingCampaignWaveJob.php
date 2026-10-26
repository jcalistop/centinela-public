<?php

namespace App\Jobs;

use App\Enums\MessagingCampaignStatus;
use App\Enums\MessagingRecipientStatus;
use App\Mail\ManualMessagingMail;
use App\Models\MessagingCampaign;
use App\Models\MessagingCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMessagingCampaignWaveJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(): void
    {
        $campaign = MessagingCampaign::query()
            ->with('sender.cenHieDependency')
            ->find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $waveSize = max(1, (int) config('centinela.messaging.wave_size', 25));
        $delaySeconds = max(0, (int) config('centinela.messaging.wave_delay_seconds', 300));

        $recipients = MessagingCampaignRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', MessagingRecipientStatus::Queued)
            ->orderBy('id')
            ->limit($waveSize)
            ->get();

        if ($recipients->isEmpty()) {
            $this->refreshCampaignCounters($campaign);

            return;
        }

        $campaign->update(['status' => MessagingCampaignStatus::Sending]);

        $sender = $campaign->sender;
        if ($sender === null) {
            $campaign->update(['status' => MessagingCampaignStatus::Failed]);

            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email)->send(new ManualMessagingMail($campaign, $sender));

                $recipient->update([
                    'status' => MessagingRecipientStatus::Sent,
                    'sent_at' => now(),
                    'error' => null,
                ]);
            } catch (Throwable $e) {
                $recipient->update([
                    'status' => MessagingRecipientStatus::Failed,
                    'error' => mb_substr($e->getMessage(), 0, 1000),
                ]);
            }
        }

        $this->refreshCampaignCounters($campaign);

        $remaining = MessagingCampaignRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', MessagingRecipientStatus::Queued)
            ->exists();

        if ($remaining) {
            self::dispatch($campaign->id)->delay(now()->addSeconds($delaySeconds));
        }
    }

    private function refreshCampaignCounters(MessagingCampaign $campaign): void
    {
        $sent = MessagingCampaignRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', MessagingRecipientStatus::Sent)
            ->count();

        $failed = MessagingCampaignRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', MessagingRecipientStatus::Failed)
            ->count();

        $queued = MessagingCampaignRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', MessagingRecipientStatus::Queued)
            ->exists();

        $status = $queued
            ? MessagingCampaignStatus::Sending
            : ($failed > 0 && $sent === 0
                ? MessagingCampaignStatus::Failed
                : MessagingCampaignStatus::Completed);

        $campaign->update([
            'recipients_sent' => $sent,
            'recipients_failed' => $failed,
            'status' => $status,
        ]);
    }
}
