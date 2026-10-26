<?php

namespace App\Livewire\Messaging;

use App\Enums\MessagingAudience;
use App\Models\User;
use App\Services\Messaging\MessagingCampaignLauncher;
use App\Support\Messaging\MessagingComposeScope;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Compose de mensajería manual en Voyager (SDD-307). Paridad con ComposesMessagingCampaign (Filament).
 */
class MessagingCampaignComposer extends Component
{
    public string $audience = '';

    public ?int $cen_hie_dependency_id = null;

    /** @var list<int> */
    public array $user_ids = [];

    public string $subject = '';

    public string $body_html = '';

    public ?int $previewCount = null;

    public bool $showConfirm = false;

    public function mount(): void
    {
        $actor = $this->requireComposer();
        $defaults = MessagingComposeScope::defaults(
            $actor,
            MessagingComposeScope::actorIsAdmin($actor),
        );

        $this->audience = $defaults['audience'];
        $this->cen_hie_dependency_id = $defaults['cen_hie_dependency_id'];
        $this->user_ids = $defaults['user_ids'];
        $this->subject = $defaults['subject'];
        $this->body_html = $defaults['body_html'];
    }

    public function updatedAudience(): void
    {
        $this->requireComposer();
        $this->previewCount = null;
        $this->showConfirm = false;

        if ($this->audience === MessagingAudience::SpecificUsers->value) {
            $this->dispatch('messaging-user-select-init');
        }
    }

    public function updatedCenHieDependencyId(): void
    {
        $this->requireComposer();
        $this->previewCount = null;
        $this->showConfirm = false;
    }

    public function updatedUserIds(): void
    {
        $this->requireComposer();
        $this->previewCount = null;
        $this->showConfirm = false;
    }

    public function previewRecipients(): void
    {
        $actor = $this->requireComposer();
        $this->validateComposer();

        try {
            $this->previewCount = app(MessagingCampaignLauncher::class)->previewCount($actor, $this->payload($actor));

            if ($this->previewCount === 0) {
                $this->dispatch('toast', type: 'warning', message: 'Sin destinatarios elegibles');
            } else {
                $this->dispatch('toast', type: 'success', message: "Destinatarios: {$this->previewCount}");
            }
        } catch (ValidationException $exception) {
            $this->previewCount = null;
            $this->mapLauncherErrors($exception);
        }
    }

    public function openConfirm(): void
    {
        $this->requireComposer();

        if ($this->previewCount === null || $this->previewCount < 1) {
            return;
        }

        $this->showConfirm = true;
    }

    public function closeConfirm(): void
    {
        $this->requireComposer();
        $this->showConfirm = false;
    }

    public function sendCampaign(): void
    {
        $actor = $this->requireComposer();
        $this->validateComposer();

        if ($this->previewCount === null || $this->previewCount < 1) {
            $this->dispatch('toast', type: 'warning', message: 'Calcule primero los destinatarios.');

            return;
        }

        $launcher = app(MessagingCampaignLauncher::class);

        try {
            $count = $launcher->previewCount($actor, $this->payload($actor));
            if ($count < 1) {
                $this->previewCount = 0;
                $this->showConfirm = false;
                $this->dispatch('toast', type: 'warning', message: 'No hay destinatarios elegibles');

                return;
            }

            $campaign = $launcher->launch($actor, $this->payload($actor));
            $this->showConfirm = false;
            $this->dispatch('toast', type: 'success', message: "Campaña encolada. ID {$campaign->id}: {$campaign->recipients_total} destinatario(s).");

            $this->subject = '';
            $this->body_html = '';
            $this->user_ids = [];
            $this->previewCount = null;
            $this->dispatch('messaging-form-reset');
            $this->dispatch('messaging-campaign-queued');
        } catch (ValidationException $exception) {
            $this->mapLauncherErrors($exception);
        }
    }

    public function render(): View
    {
        $actor = $this->requireComposer();
        $isAdmin = MessagingComposeScope::actorIsAdmin($actor);

        /** @var View|\Illuminate\View\View $view */
        $view = view('livewire.messaging.composer', [
            'isAdmin' => $isAdmin,
            'audienceOptions' => MessagingComposeScope::audienceOptions($isAdmin),
            'healthServiceOptions' => MessagingComposeScope::healthServiceOptions(),
            'userOptions' => MessagingComposeScope::userOptions($actor, $isAdmin),
            'ssAudiences' => [
                MessagingAudience::HealthServiceAll->value,
                MessagingAudience::HealthServiceReferentes->value,
            ],
            'specificUsers' => MessagingAudience::SpecificUsers->value,
        ]);

        return $view->extends('voyager::master')->section('content');
    }

    protected function requireComposer(): User
    {
        $user = Auth::user();

        if (! $user instanceof User || ! MessagingComposeScope::canCompose($user)) {
            abort(403);
        }

        return $user;
    }

    /**
     * @return array{audience: string, cen_hie_dependency_id: int|null, user_ids: list<int>, subject: string, body_html: string}
     */
    private function payload(User $actor): array
    {
        $isAdmin = MessagingComposeScope::actorIsAdmin($actor);
        $ssId = $isAdmin ? $this->cen_hie_dependency_id : $actor->cen_hie_dependency_id;

        return MessagingComposeScope::payloadFromState([
            'audience' => $this->audience,
            'cen_hie_dependency_id' => $ssId,
            'user_ids' => $this->user_ids,
            'subject' => $this->subject,
            'body_html' => $this->body_html,
        ]);
    }

    private function validateComposer(): void
    {
        $actor = $this->requireComposer();
        $isAdmin = MessagingComposeScope::actorIsAdmin($actor);
        $allowed = implode(',', array_keys(MessagingComposeScope::audienceOptions($isAdmin)));

        $rules = [
            'audience' => 'required|in:'.$allowed,
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'user_ids' => 'array',
        ];

        if ($this->audience === MessagingAudience::SpecificUsers->value) {
            $rules['user_ids'] = 'required|array|min:1';
        }

        if ($isAdmin && in_array($this->audience, [
            MessagingAudience::HealthServiceAll->value,
            MessagingAudience::HealthServiceReferentes->value,
        ], true)) {
            $rules['cen_hie_dependency_id'] = 'required|integer';
        }

        $this->validate($rules);

        if (! $isAdmin && $this->cen_hie_dependency_id !== null
            && $actor->cen_hie_dependency_id !== null
            && (int) $this->cen_hie_dependency_id !== (int) $actor->cen_hie_dependency_id) {
            $this->addError('cen_hie_dependency_id', 'Solo puede enviar a su propio Servicio de Salud.');

            throw ValidationException::withMessages([
                'cen_hie_dependency_id' => 'Solo puede enviar a su propio Servicio de Salud.',
            ]);
        }

        $plain = trim(html_entity_decode(strip_tags($this->body_html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plain === '') {
            $this->addError('body_html', 'El mensaje no puede estar vacío.');

            throw ValidationException::withMessages([
                'body_html' => 'El mensaje no puede estar vacío.',
            ]);
        }
    }

    private function mapLauncherErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError((string) $key, $message);
            }
        }
    }
}
