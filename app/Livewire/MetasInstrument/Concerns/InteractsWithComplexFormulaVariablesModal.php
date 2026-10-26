<?php

namespace App\Livewire\MetasInstrument\Concerns;

use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetComplexFormulaVariablesModalService;
use Illuminate\Support\Facades\Auth;

trait InteractsWithComplexFormulaVariablesModal
{
    public bool $showComplexFormulaVariablesModal = false;

    /** @var array<string, mixed>|null */
    public ?array $complexFormulaVariablesPayload = null;

    public function openComplexFormulaVariablesModal(
        int $indicatorId,
        int $centerId,
        ?string $periodo = null,
        bool $periodoIsReferenceFromSummary = false,
    ): void {
        if ($periodo === '') {
            $periodo = null;
        }

        $this->complexFormulaVariablesPayload = app(MetComplexFormulaVariablesModalService::class)->payload(
            $this->complexFormulaVariablesModalUser(),
            $this->complexFormulaVariablesModalIndicatorTypeId(),
            $indicatorId,
            $centerId,
            $this->complexFormulaVariablesModalYearId(),
            $periodo,
            $periodoIsReferenceFromSummary,
        );
        $this->showComplexFormulaVariablesModal = true;
    }

    public function closeComplexFormulaVariablesModal(): void
    {
        $this->showComplexFormulaVariablesModal = false;
        $this->complexFormulaVariablesPayload = null;
    }

    abstract protected function complexFormulaVariablesModalIndicatorTypeId(): int;

    protected function complexFormulaVariablesModalUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function complexFormulaVariablesModalYearId(): int
    {
        $year = session('indicator_year');
        abort_unless($year instanceof Year, 403);

        return (int) $year->id;
    }
}
