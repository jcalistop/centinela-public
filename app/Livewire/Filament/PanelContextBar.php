<?php

namespace App\Livewire\Filament;

use App\Models\Year;
use App\Support\Voyager\YearDropdownCatalog;
use App\Support\YearContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Año activo + etiqueta SS en topbar Filament (paridad navbar Voyager; SDD-275 tweak).
 */
class PanelContextBar extends Component
{
    public ?int $yearId = null;

    public function mount(YearContext $yearContext): void
    {
        $current = $yearContext->current();
        $this->yearId = $current?->id;
    }

    public function updatedYearId(mixed $value): void
    {
        $yearId = filter_var($value, FILTER_VALIDATE_INT);
        if ($yearId === false) {
            return;
        }

        $year = Year::query()->whereKey($yearId)->firstOrFail();
        app(YearContext::class)->setCurrent($year);
        $this->yearId = $year->id;

        $this->redirect(request()->header('Referer') ?: url()->previous() ?: filament()->getUrl(), navigate: false);
    }

    public function render(): View
    {
        /** @var Collection<int, Year> $years */
        $years = YearDropdownCatalog::all();

        return view('livewire.filament.panel-context-bar', [
            'years' => $years,
            'ssName' => Auth::user()?->cenHieDependency?->nombre,
        ]);
    }
}
