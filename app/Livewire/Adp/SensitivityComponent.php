<?php

namespace App\Livewire\Adp;

use App\Livewire\Adp\Traits\ResolvesAdpInstrument;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpSensitivityTable;
use App\Models\AdpSensitivityTableRow;
use App\Services\Adp\AdpSensitivityRowsValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SensitivityComponent extends Component
{
    use ResolvesAdpInstrument;

    public ?int $selectedAgreementId = null;

    public ?int $selectedPeriodId = null;

    public ?int $selectedIndicatorId = null;

    public ?int $editingTableId = null;

    public string $tableName = '';

    // Row management (trabajamos con un array para edición inline)
    /** @var array<int, array{id?: int, orden: int, valor_minimo: string, operador_minimo: string, valor_maximo: string, operador_maximo: string, cumplimiento: string}> */
    public array $rows = [];

    public function mount(?int $indicatorTypeId = null): void
    {
        $this->indicatorTypeId = $this->resolveIndicatorTypeId($indicatorTypeId);
    }

    public function render(): View
    {
        $user = $this->authUser();
        $canEdit = $user->hasRole(['admin', 'control-gestion']);

        $agreements = AdpAgreement::query()
            ->with('assignment.user', 'assignment.position')
            ->get();

        $periods = collect();
        $indicators = collect();
        $table = null;

        if ($this->selectedAgreementId !== null) {
            $periods = AdpAgreementPeriod::query()
                ->where('adp_agreement_id', $this->selectedAgreementId)
                ->orderBy('sequence')
                ->get();
        }

        if ($this->selectedPeriodId !== null) {
            $indicators = AdpIndicator::query()
                ->whereHas('objective', fn ($q) => $q->where('adp_agreement_period_id', $this->selectedPeriodId))
                ->with('objective')
                ->orderBy('clave')
                ->get();
        }

        if ($this->selectedIndicatorId !== null) {
            /** @var AdpSensitivityTable|null $table */
            $table = AdpSensitivityTable::query()
                ->where('adp_indicator_id', $this->selectedIndicatorId)
                ->with('rows')
                ->first();
        }

        return view('livewire.adp.sensitivity', compact(
            'agreements', 'periods', 'indicators', 'table', 'canEdit'
        ));
    }

    public function selectAgreement(int $id): void
    {
        $this->selectedAgreementId = $id;
        $this->selectedPeriodId = null;
        $this->selectedIndicatorId = null;
        $this->editingTableId = null;
        $this->rows = [];
    }

    public function selectPeriod(int $id): void
    {
        $this->selectedPeriodId = $id;
        $this->selectedIndicatorId = null;
        $this->editingTableId = null;
        $this->rows = [];
    }

    public function selectIndicator(int $id): void
    {
        $this->selectedIndicatorId = $id;
        $this->editingTableId = null;
        $this->rows = [];
    }

    public function createTable(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);
        abort_unless($this->selectedIndicatorId !== null, 403);

        $this->editingTableId = null;
        $this->tableName = '';
        $this->rows = [];
        $this->addRow();
    }

    public function editTable(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $table = AdpSensitivityTable::query()->with('rows')->findOrFail($id);
        $this->editingTableId = $table->id;
        $this->tableName = $table->nombre;
        $this->rows = [];

        foreach ($table->rows->sortBy('orden') as $row) {
            $this->rows[] = [
                'id' => $row->id,
                'orden' => $row->orden,
                'valor_minimo' => (string) $row->valor_minimo,
                'operador_minimo' => $row->operador_minimo,
                'valor_maximo' => (string) $row->valor_maximo,
                'operador_maximo' => $row->operador_maximo,
                'cumplimiento' => (string) $row->cumplimiento,
            ];
        }

        // Asegurar al menos una fila visible
        if (empty($this->rows)) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->rows[] = [
            'orden' => count($this->rows) + 1,
            'valor_minimo' => '0',
            'operador_minimo' => '>=',
            'valor_maximo' => '0',
            'operador_maximo' => '<=',
            'cumplimiento' => '0',
        ];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);

        // Re-number
        foreach ($this->rows as $i => &$row) {
            $row['orden'] = $i + 1;
        }
    }

    public function saveTable(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);
        abort_unless($this->selectedIndicatorId !== null, 403);

        $this->validate([
            'tableName' => 'required|string|max:255',
        ]);

        // Build normalized rows for validator
        $normalized = [];
        foreach ($this->rows as $row) {
            $normalized[] = [
                'orden' => (int) ($row['orden'] ?? 0),
                'valor_minimo' => (float) ($row['valor_minimo'] ?? 0),
                'operador_minimo' => $row['operador_minimo'] ?? '>=',
                'valor_maximo' => (float) ($row['valor_maximo'] ?? 0),
                'operador_maximo' => $row['operador_maximo'] ?? '<=',
                'cumplimiento' => (float) ($row['cumplimiento'] ?? 0),
            ];
        }

        try {
            AdpSensitivityRowsValidator::validate($normalized);
        } catch (ValidationException $e) {
            $this->addError('rows', $e->getMessage());

            return;
        }

        DB::transaction(function () use ($normalized): void {
            if ($this->editingTableId !== null) {
                $table = AdpSensitivityTable::query()->findOrFail($this->editingTableId);
                $table->update(['nombre' => $this->tableName]);

                // Delete existing rows and re-create
                $table->rows()->delete();
            } else {
                $table = AdpSensitivityTable::query()->create([
                    'adp_indicator_id' => $this->selectedIndicatorId,
                    'nombre' => $this->tableName,
                ]);
            }

            foreach ($normalized as $row) {
                AdpSensitivityTableRow::query()->create([
                    'adp_sensitivity_table_id' => $table->id,
                    'orden' => $row['orden'],
                    'valor_minimo' => $row['valor_minimo'],
                    'operador_minimo' => $row['operador_minimo'],
                    'valor_maximo' => $row['valor_maximo'],
                    'operador_maximo' => $row['operador_maximo'],
                    'cumplimiento' => $row['cumplimiento'],
                ]);
            }

            $this->editingTableId = $table->id;
            $this->dispatch('toast', type: 'success', message: 'Tabla de sensibilidad guardada.');
        });
    }

    public function cancelTableForm(): void
    {
        $this->editingTableId = null;
        $this->tableName = '';
        $this->rows = [];
    }

    public function deleteTable(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $table = AdpSensitivityTable::query()->findOrFail($id);
        $table->delete();

        $this->editingTableId = null;
        $this->rows = [];
        $this->dispatch('toast', type: 'success', message: 'Tabla de sensibilidad eliminada.');
    }
}
