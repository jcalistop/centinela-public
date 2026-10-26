<?php

namespace App\Support\Messaging;

use App\Enums\MessagingAudience;
use App\Models\CenHieDependency;
use App\Models\MessagingCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alcance no-UI del compose de mensajería (SDD-307). Sin FilamentMessagingGate.
 */
final class MessagingComposeScope
{
    public static function canCompose(User $actor): bool
    {
        return $actor->hasRole('admin') || $actor->hasRole('control-gestion');
    }

    public static function actorIsAdmin(User $actor): bool
    {
        return $actor->hasRole('admin');
    }

    /**
     * @return array{audience: string, cen_hie_dependency_id: int|null, user_ids: list<int>, subject: string, body_html: string}
     */
    public static function defaults(User $actor, bool $isAdmin): array
    {
        return [
            'audience' => $isAdmin
                ? MessagingAudience::AllUsers->value
                : MessagingAudience::HealthServiceAll->value,
            'cen_hie_dependency_id' => $isAdmin ? null : $actor->cen_hie_dependency_id,
            'user_ids' => [],
            'subject' => '',
            'body_html' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function audienceOptions(bool $isAdmin): array
    {
        $options = [
            MessagingAudience::HealthServiceAll->value => MessagingAudience::HealthServiceAll->label(),
            MessagingAudience::HealthServiceReferentes->value => MessagingAudience::HealthServiceReferentes->label(),
            MessagingAudience::SpecificUsers->value => MessagingAudience::SpecificUsers->label(),
        ];

        if ($isAdmin) {
            return [
                MessagingAudience::AllUsers->value => MessagingAudience::AllUsers->label(),
                ...$options,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function healthServiceOptions(): array
    {
        return CenHieDependency::query()
            ->enabled()
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function userOptions(User $actor, bool $isAdmin): array
    {
        $query = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('name');

        if (! $isAdmin) {
            if ($actor->cen_hie_dependency_id === null) {
                return [];
            }
            $query->where('cen_hie_dependency_id', $actor->cen_hie_dependency_id);
        }

        return $query
            ->limit(500)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} <{$user->email}>"])
            ->all();
    }

    /**
     * @return Builder<MessagingCampaign>
     */
    public static function campaignsQuery(User $actor, bool $isAdmin): Builder
    {
        $query = MessagingCampaign::query()->with(['sender', 'healthService']);

        if (! $isAdmin) {
            $query->where('sender_id', $actor->id);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{audience: string, cen_hie_dependency_id: int|null, user_ids: list<int>, subject: string, body_html: string}
     */
    public static function payloadFromState(array $state): array
    {
        $userIds = $state['user_ids'] ?? [];
        if (! is_array($userIds)) {
            $userIds = [];
        }

        return [
            'audience' => (string) $state['audience'],
            'cen_hie_dependency_id' => isset($state['cen_hie_dependency_id'])
                ? (int) $state['cen_hie_dependency_id']
                : null,
            'user_ids' => array_map('intval', array_values($userIds)),
            'subject' => (string) $state['subject'],
            'body_html' => (string) $state['body_html'],
        ];
    }
}
