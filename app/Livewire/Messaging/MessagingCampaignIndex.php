<?php

namespace App\Livewire\Messaging;

use App\Models\MessagingCampaign;
use App\Models\User;
use App\Support\Messaging\MessagingComposeScope;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de campañas de mensajería en Voyager (SDD-307). Anidado en MessagingCampaignComposer.
 */
class MessagingCampaignIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public bool $showDetail = false;

    public ?int $detailId = null;

    protected string $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->requireComposer();
    }

    public function updatingSearch(): void
    {
        $this->requireComposer();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->requireComposer();
        $this->resetPage();
    }

    #[On('messaging-campaign-queued')]
    public function refreshAfterQueue(): void
    {
        $this->requireComposer();
        $this->resetPage();
    }

    public function openDetail(int $id): void
    {
        $this->requireComposer();
        $this->findCampaignOrFail($id);
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->requireComposer();
        $this->showDetail = false;
        $this->detailId = null;
    }

    public function render(): View
    {
        $actor = $this->requireComposer();
        $isAdmin = MessagingComposeScope::actorIsAdmin($actor);
        $term = trim($this->search);
        $perPage = in_array($this->perPage, [10, 25, 50], true) ? $this->perPage : 10;

        $campaigns = MessagingComposeScope::campaignsQuery($actor, $isAdmin)
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where('subject', 'like', $like);
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $detail = null;
        if ($this->showDetail && $this->detailId !== null) {
            $detail = $this->findCampaignOrFail($this->detailId);
        }

        return view('livewire.messaging.index', [
            'campaigns' => $campaigns,
            'isAdmin' => $isAdmin,
            'detail' => $detail,
        ]);
    }

    protected function requireComposer(): User
    {
        $user = Auth::user();

        if (! $user instanceof User || ! MessagingComposeScope::canCompose($user)) {
            abort(403);
        }

        return $user;
    }

    private function findCampaignOrFail(int $id): MessagingCampaign
    {
        $actor = $this->requireComposer();
        $query = MessagingCampaign::query()->with(['sender', 'healthService']);

        if (! MessagingComposeScope::actorIsAdmin($actor)) {
            $query->where('sender_id', $actor->id);
        }

        return $query->findOrFail($id);
    }
}
