<?php

namespace App\Services\Auth;

use App\Enums\SupportTicketArea;
use App\Models\SupportTicketReceiver;
use App\Support\Configuration\ConfigurationService;

class AccessRequestRecipientResolver
{
    public function __construct(
        private readonly ConfigurationService $configuration,
    ) {}

    public function resolveEmailForServicio(int $cenHieDependencyId): string
    {
        $primary = SupportTicketReceiver::query()
            ->where('enabled', true)
            ->where('is_primary', true)
            ->where('scope', SupportTicketReceiver::SCOPE_SS)
            ->where('area', SupportTicketArea::Gestion)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->with('user')
            ->first();

        $email = trim((string) ($primary?->user?->email ?? ''));
        if ($email !== '') {
            return $email;
        }

        return $this->resolveAdminFallbackEmail();
    }

    public function resolveAdminFallbackEmail(): string
    {
        return trim((string) $this->configuration->get(
            'notifications.contact_mail_to',
            config('centinela.contact_mail_to')
        ));
    }

    public function hasPrimaryGestionReceiver(int $cenHieDependencyId): bool
    {
        $primary = SupportTicketReceiver::query()
            ->where('enabled', true)
            ->where('is_primary', true)
            ->where('scope', SupportTicketReceiver::SCOPE_SS)
            ->where('area', SupportTicketArea::Gestion)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->with('user')
            ->first();

        if ($primary === null) {
            return false;
        }

        return trim((string) ($primary->user?->email ?? '')) !== '';
    }
}
