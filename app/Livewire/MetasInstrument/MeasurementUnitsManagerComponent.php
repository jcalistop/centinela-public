<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetMeasurementUnit;
use App\Models\MetStageTransition;
use App\Models\MetUnitType;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetEstablishmentAgreementService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MeasurementUnitsManagerComponent extends Component
{
    public int $centerId;

    public int $indicatorTypeId;

    public string $newCodigo = '';

    public string $newNombre = '';

    public ?int $newMetUnitTypeId = null;

    public ?int $editingUnitId = null;

    public string $editCodigo = '';

    public string $editNombre = '';

    public ?int $editMetUnitTypeId = null;

    public ?int $pendingDeleteUnitId = null;

    public bool $confirmCreateDespiteEnviado = false;

    public string $unitCreationMotivo = '';

    public function mount(int $centerId, int $indicatorTypeId): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $this->centerId = $centerId;
        $this->indicatorTypeId = $indicatorTypeId;

        $this->assertInternalUnitInstrument();
        $this->assertCenterAccess();
        $this->unitCreationMotivo = 'Creación de unidad de medición en establecimiento con convenio enviado.';
    }

    public function saveNewUnit(): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        if (! $this->assertInstrumentOpen()) {
            return;
        }

        $this->assertInternalUnitInstrument();
        $this->assertCenterAccess();

        $yearId = $this->currentYear()->id;
        $agreementService = app(MetEstablishmentAgreementService::class);
        $isEnviado = $agreementService->isEnviado($this->centerId, $yearId, $this->indicatorTypeId);

        if ($isEnviado && ! $this->confirmCreateDespiteEnviado) {
            $this->addError(
                'newCodigo',
                'Este centro tiene convenio enviado. Confirme la creación: el retroceso a Formulación alcanza a todo el SS y solo este centro volverá a borrador.',
            );

            return;
        }

        if ($isEnviado) {
            $this->validate([
                'unitCreationMotivo' => ['required', 'string', 'min:3'],
            ], [], [
                'unitCreationMotivo' => 'motivo',
            ]);
        }

        $this->validate([
            'newCodigo' => [
                'required',
                'string',
                'max:64',
                Rule::unique('met_measurement_units', 'codigo')
                    ->where(fn ($query) => $query
                        ->where('cen_center_id', $this->centerId)
                        ->where('year_id', $yearId)),
            ],
            'newNombre' => ['nullable', 'string', 'max:200'],
            'newMetUnitTypeId' => ['required', 'integer', 'exists:met_unit_types,id'],
        ], [], [
            'newCodigo' => 'código de unidad',
            'newMetUnitTypeId' => 'tipo de unidad',
        ]);

        if ($isEnviado) {
            $this->rollbackStageAndAgreementForNewUnit($yearId, $agreementService);
        }

        MetMeasurementUnit::query()->create([
            'cen_center_id' => $this->centerId,
            'year_id' => $yearId,
            'met_unit_type_id' => $this->newMetUnitTypeId,
            'codigo' => trim($this->newCodigo),
            'nombre' => $this->newNombre !== '' ? trim($this->newNombre) : null,
            'activo' => true,
            'cen_hie_dependency_id' => $this->authUser()->cen_hie_dependency_id,
        ]);

        $this->reset(['newCodigo', 'newNombre', 'newMetUnitTypeId', 'confirmCreateDespiteEnviado']);
        $this->unitCreationMotivo = 'Creación de unidad de medición en establecimiento con convenio enviado.';
        $this->dispatch('units-updated');
        session()->flash('units_message', 'Unidad creada correctamente.');
    }

    private function rollbackStageAndAgreementForNewUnit(int $yearId, MetEstablishmentAgreementService $agreementService): void
    {
        $user = $this->authUser();
        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if ($setting && $setting->etapa_vigente !== EtapaVigente::Formulacion) {
            $etapaAnterior = $setting->etapa_vigente;
            $setting->update([
                'etapa_vigente' => EtapaVigente::Formulacion,
                'habilitado' => true,
            ]);

            MetStageTransition::query()->create([
                'indicator_type_id' => $this->indicatorTypeId,
                'cen_hie_dependency_id' => (int) $user->cen_hie_dependency_id,
                'year_id' => $yearId,
                'etapa_desde' => $etapaAnterior,
                'etapa_hasta' => EtapaVigente::Formulacion,
                'motivo' => $this->unitCreationMotivo,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);
        }

        $agreementService->returnToBorradorForNewUnit(
            $this->centerId,
            $yearId,
            $this->indicatorTypeId,
            (int) $user->cen_hie_dependency_id,
        );
    }

    public function startEdit(int $unitId): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        if (! $this->assertInstrumentOpen()) {
            return;
        }

        $this->assertCenterAccess();
        $this->cancelDelete();

        $unit = $this->findScopedUnit($unitId);

        $this->editingUnitId = $unit->id;
        $this->editCodigo = $unit->codigo;
        $this->editNombre = $unit->nombre ?? '';
        $this->editMetUnitTypeId = $unit->met_unit_type_id;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingUnitId', 'editCodigo', 'editNombre', 'editMetUnitTypeId']);
    }

    public function updateUnit(): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        if (! $this->assertInstrumentOpen()) {
            return;
        }

        $this->assertInternalUnitInstrument();
        $this->assertCenterAccess();

        abort_unless($this->editingUnitId !== null, 400);

        $yearId = $this->currentYear()->id;

        $this->validate([
            'editCodigo' => [
                'required',
                'string',
                'max:64',
                Rule::unique('met_measurement_units', 'codigo')
                    ->where(fn ($query) => $query
                        ->where('cen_center_id', $this->centerId)
                        ->where('year_id', $yearId))
                    ->ignore($this->editingUnitId),
            ],
            'editNombre' => ['nullable', 'string', 'max:200'],
            'editMetUnitTypeId' => ['required', 'integer', 'exists:met_unit_types,id'],
        ], [], [
            'editCodigo' => 'código de unidad',
            'editMetUnitTypeId' => 'tipo de unidad',
        ]);

        $unit = $this->findScopedUnit($this->editingUnitId);

        $unit->update([
            'codigo' => trim($this->editCodigo),
            'nombre' => $this->editNombre !== '' ? trim($this->editNombre) : null,
            'met_unit_type_id' => $this->editMetUnitTypeId,
        ]);

        if ($unit->monthlyDataRows()->exists() && $unit->wasChanged('codigo')) {
            session()->flash('units_info', 'El código cambió. Use el código actualizado en futuras importaciones Excel.');
        }

        $this->cancelEdit();
        $this->dispatch('units-updated');
        session()->flash('units_message', 'Unidad actualizada correctamente.');
    }

    public function confirmDeleteUnit(int $unitId): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        if (! $this->assertInstrumentOpen()) {
            return;
        }

        $this->assertCenterAccess();
        $this->cancelEdit();

        $this->findScopedUnit($unitId);
        $this->pendingDeleteUnitId = $unitId;
    }

    public function cancelDelete(): void
    {
        $this->pendingDeleteUnitId = null;
    }

    public function deleteUnit(): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        if (! $this->assertInstrumentOpen()) {
            return;
        }

        $this->assertInternalUnitInstrument();
        $this->assertCenterAccess();

        abort_unless($this->pendingDeleteUnitId !== null, 400);

        $unit = $this->findScopedUnit($this->pendingDeleteUnitId);

        if ($unit->isInUse()) {
            session()->flash('units_error', $unit->usageBlockMessage());
            $this->cancelDelete();

            return;
        }

        $codigo = $unit->codigo;
        $unit->delete();

        $this->cancelDelete();
        $this->dispatch('units-updated');
        session()->flash('units_message', "Unidad «{$codigo}» eliminada correctamente.");
    }

    public function toggleActivo(int $unitId): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        if (! $this->assertInstrumentOpen()) {
            return;
        }

        $this->assertInternalUnitInstrument();
        $this->assertCenterAccess();

        $unit = $this->findScopedUnit($unitId);

        $unit->update(['activo' => ! $unit->activo]);
        $this->dispatch('units-updated');
        session()->flash('units_message', $unit->activo ? 'Unidad activada.' : 'Unidad desactivada.');
    }

    public function render(): View
    {
        $this->assertInternalUnitInstrument();

        $units = MetMeasurementUnit::query()
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $this->currentYear()->id)
            ->with('unitType')
            ->orderBy('codigo')
            ->get();

        $unitTypes = MetUnitType::query()
            ->where('enabled', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $editUnitTypes = $this->editingUnitId !== null
            ? $this->unitTypesForSelect($this->editMetUnitTypeId)
            : collect();

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);
        $isCerrado = ! $setting->etapa_vigente->permiteConfiguracionEstructural();
        $hasEnviadoAgreement = app(MetEstablishmentAgreementService::class)->isEnviado(
            $this->centerId,
            $this->currentYear()->id,
            $this->indicatorTypeId,
        );

        return view('livewire.metas-instrument.measurement-units-manager', compact(
            'units',
            'unitTypes',
            'editUnitTypes',
            'isCerrado',
            'hasEnviadoAgreement',
        ));
    }

    private function findScopedUnit(int $unitId): MetMeasurementUnit
    {
        return MetMeasurementUnit::query()
            ->where('id', $unitId)
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $this->currentYear()->id)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, MetUnitType>
     */
    private function unitTypesForSelect(?int $currentTypeId): Collection
    {
        return MetUnitType::query()
            ->where(function ($query) use ($currentTypeId): void {
                $query->where('enabled', true);

                if ($currentTypeId !== null) {
                    $query->orWhere('id', $currentTypeId);
                }
            })
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    private function assertInstrumentOpen(): bool
    {
        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if (! $setting->etapa_vigente->permiteConfiguracionEstructural()) {
            session()->flash('units_error', 'No se puede modificar en la etapa vigente: '.$setting->etapa_vigente->label().'.');

            return false;
        }

        return true;
    }

    private function assertInternalUnitInstrument(): void
    {
        $type = IndicatorType::query()->findOrFail($this->indicatorTypeId);
        abort_unless($type->usesInternalUnitMeasurement(), 404);
    }

    private function assertCenterAccess(): void
    {
        $center = CenCenter::query()
            ->where('id', $this->centerId)
            ->where('cen_hie_dependency_id', $this->authUser()->cen_hie_dependency_id)
            ->first();
        abort_unless($center !== null, 403);
    }

    private function authUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function currentYear(): Year
    {
        $year = session('indicator_year');
        abort_unless($year instanceof Year, 500);

        return $year;
    }
}
