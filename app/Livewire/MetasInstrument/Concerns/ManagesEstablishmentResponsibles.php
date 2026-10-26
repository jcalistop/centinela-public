<?php

namespace App\Livewire\MetasInstrument\Concerns;

use App\Models\CenCenter;
use App\Models\MetEstablishmentResponsible;
use App\Models\User;
use App\Services\Metas\MetEstablishmentResponsibleAccess;
use App\Services\Metas\MetStageAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

trait ManagesEstablishmentResponsibles
{
    public bool $showResponsibleModal = false;

    public ?int $responsibleCenterId = null;

    public string $responsibleCenterName = '';

    public ?int $selectedResponsibleUserId = null;

    /** @var array<int, list<array{id: int, name: string}>> */
    public array $designeesByCenter = [];

    protected function shouldManageEstablishmentResponsibles(): bool
    {
        return true;
    }

    public function loadDesigneesByCenter(): void
    {
        if (! $this->shouldManageEstablishmentResponsibles()) {
            $this->designeesByCenter = [];

            return;
        }

        $yearId = $this->indicatorYear()->id;
        $depId = (int) $this->authenticatedUser()->cen_hie_dependency_id;

        $rows = MetEstablishmentResponsible::query()
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('cen_hie_dependency_id', $depId)
            ->with('user')
            ->orderBy('cen_center_id')
            ->get();

        $this->designeesByCenter = [];
        foreach ($rows as $row) {
            $centerId = (int) $row->cen_center_id;
            $this->designeesByCenter[$centerId] ??= [];
            $this->designeesByCenter[$centerId][] = [
                'id' => (int) $row->user_id,
                'name' => $row->user?->name ?? '—',
            ];
        }
    }

    public function openDesignateModal(int $centerId): void
    {
        if (! app(MetStageAccessService::class)->canDesignateEstablishmentResponsible($this->authenticatedUser())) {
            abort(403);
        }

        $center = CenCenter::query()->findOrFail($centerId);
        $this->responsibleCenterId = $centerId;
        $this->responsibleCenterName = (string) $center->nombre_corto;
        $this->selectedResponsibleUserId = null;
        $this->showResponsibleModal = true;
        $this->dispatch('open-responsible-select2', centerId: $centerId);
    }

    public function closeResponsibleModal(): void
    {
        $this->showResponsibleModal = false;
        $this->responsibleCenterId = null;
        $this->selectedResponsibleUserId = null;
    }

    public function saveDesignation(?int $userId = null): void
    {
        if (! app(MetStageAccessService::class)->canDesignateEstablishmentResponsible($this->authenticatedUser())) {
            abort(403);
        }

        if ($this->responsibleCenterId === null) {
            return;
        }

        $userId = $userId ?? $this->selectedResponsibleUserId;
        if ($userId === null) {
            session()->flash('stage_designee_error', 'Debe seleccionar un usuario responsable.');

            return;
        }

        $yearId = $this->indicatorYear()->id;
        $depId = (int) $this->authenticatedUser()->cen_hie_dependency_id;
        $access = app(MetEstablishmentResponsibleAccess::class);

        if ($access->userAlreadyResponsibleElsewhere($userId, $this->responsibleCenterId, $yearId, $this->indicatorTypeId, $depId)) {
            session()->flash(
                'stage_designee_error',
                'Ese usuario ya es responsable de otro establecimiento para este instrumento y año.',
            );

            return;
        }

        $alreadyOnCenter = MetEstablishmentResponsible::query()
            ->where('cen_center_id', $this->responsibleCenterId)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyOnCenter) {
            session()->flash('stage_designee_error', 'Ese usuario ya está designado en este establecimiento.');

            return;
        }

        try {
            MetEstablishmentResponsible::query()->create([
                'cen_center_id' => $this->responsibleCenterId,
                'year_id' => $yearId,
                'indicator_type_id' => $this->indicatorTypeId,
                'cen_hie_dependency_id' => $depId,
                'user_id' => $userId,
                'assigned_by' => $this->authenticatedUser()->id,
            ]);
        } catch (QueryException) {
            session()->flash(
                'stage_designee_error',
                'No se pudo designar al usuario. Verifique que no esté asignado a otro establecimiento.',
            );

            return;
        }

        $this->loadDesigneesByCenter();
        $this->closeResponsibleModal();
        session()->flash('stage_designee_message', 'Responsable designado correctamente.');
    }

    public function removeDesignation(int $centerId, int $userId): void
    {
        if (! app(MetStageAccessService::class)->canDesignateEstablishmentResponsible($this->authenticatedUser())) {
            abort(403);
        }

        $yearId = $this->indicatorYear()->id;
        $depId = (int) $this->authenticatedUser()->cen_hie_dependency_id;

        MetEstablishmentResponsible::query()
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('cen_hie_dependency_id', $depId)
            ->where('user_id', $userId)
            ->delete();

        $this->loadDesigneesByCenter();
        session()->flash('stage_designee_message', 'Responsable eliminado.');
    }

    /**
     * @return Collection<int, User>
     */
    protected function referenteUsersForDesignation(): Collection
    {
        return User::query()
            ->where('cen_hie_dependency_id', $this->authenticatedUser()->cen_hie_dependency_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'referente'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function canDesignateEstablishmentResponsibles(): bool
    {
        return app(MetStageAccessService::class)->canDesignateEstablishmentResponsible($this->authenticatedUser());
    }
}
