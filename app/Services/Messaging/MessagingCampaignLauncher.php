<?php

namespace App\Services\Messaging;

use App\Enums\MessagingAudience;
use App\Enums\MessagingCampaignStatus;
use App\Enums\MessagingRecipientStatus;
use App\Jobs\SendMessagingCampaignWaveJob;
use App\Models\MessagingCampaign;
use App\Models\MessagingCampaignRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MessagingCampaignLauncher
{
    public function __construct(
        private readonly MessagingAudienceResolver $audienceResolver,
    ) {}

    /**
     * @param  array{
     *     audience: MessagingAudience|string,
     *     cen_hie_dependency_id?: int|null,
     *     user_ids?: list<int>|null,
     *     subject: string,
     *     body_html: string,
     * }  $payload
     */
    public function previewCount(User $actor, array $payload): int
    {
        $audience = $payload['audience'] instanceof MessagingAudience
            ? $payload['audience']
            : MessagingAudience::from((string) $payload['audience']);

        $requestedSsId = isset($payload['cen_hie_dependency_id']) ? (int) $payload['cen_hie_dependency_id'] : null;
        $userIds = $payload['user_ids'] ?? null;

        $this->audienceResolver->assertActorCanReachAudience(
            $actor,
            $audience,
            $requestedSsId,
            $userIds,
        );

        $ssId = $requestedSsId;
        if ($actor->hasRole('control-gestion') && ! $actor->hasRole('admin')) {
            $ssId = $actor->cen_hie_dependency_id;
        }

        return $this->audienceResolver->resolve(
            $actor,
            $audience,
            $ssId,
            $userIds,
        )->count();
    }

    /**
     * @param  array{
     *     audience: MessagingAudience|string,
     *     cen_hie_dependency_id?: int|null,
     *     user_ids?: list<int>|null,
     *     subject: string,
     *     body_html: string,
     * }  $payload
     */
    public function launch(User $actor, array $payload): MessagingCampaign
    {
        $audience = $payload['audience'] instanceof MessagingAudience
            ? $payload['audience']
            : MessagingAudience::from((string) $payload['audience']);

        $requestedSsId = isset($payload['cen_hie_dependency_id']) ? (int) $payload['cen_hie_dependency_id'] : null;
        $userIds = $payload['user_ids'] ?? null;

        $this->audienceResolver->assertActorCanReachAudience(
            $actor,
            $audience,
            $requestedSsId,
            $userIds,
        );

        $ssId = $requestedSsId;
        if ($actor->hasRole('control-gestion') && ! $actor->hasRole('admin')) {
            $ssId = $actor->cen_hie_dependency_id;
        }

        $recipients = $this->audienceResolver->resolve(
            $actor,
            $audience,
            $ssId,
            $userIds,
        );

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'audience' => 'No hay destinatarios elegibles (correo vacío o filtros sin resultados).',
            ]);
        }

        $bodyHtml = $this->sanitizeHtml((string) $payload['body_html']);
        $bodyText = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $campaign = DB::transaction(function () use ($actor, $audience, $ssId, $payload, $bodyHtml, $bodyText, $recipients): MessagingCampaign {
            $campaign = MessagingCampaign::query()->create([
                'sender_id' => $actor->id,
                'audience' => $audience,
                'cen_hie_dependency_id' => in_array($audience, [
                    MessagingAudience::HealthServiceAll,
                    MessagingAudience::HealthServiceReferentes,
                ], true) ? $ssId : null,
                'subject' => (string) $payload['subject'],
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'status' => MessagingCampaignStatus::Queued,
                'recipients_total' => $recipients->count(),
                'recipients_sent' => 0,
                'recipients_failed' => 0,
            ]);

            foreach ($recipients as $user) {
                MessagingCampaignRecipient::query()->create([
                    'messaging_campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                    'email' => (string) $user->email,
                    'status' => MessagingRecipientStatus::Queued,
                ]);
            }

            return $campaign;
        });

        SendMessagingCampaignWaveJob::dispatch($campaign->id);

        return $campaign;
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote>';

        return strip_tags($html, $allowed);
    }
}
