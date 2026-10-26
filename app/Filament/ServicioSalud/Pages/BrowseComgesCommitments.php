<?php

namespace App\Filament\ServicioSalud\Pages;

use App\Helpers\ModuleAccess;
use App\Models\User;
use App\Support\Comges\FilamentComgesBrowseGate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * C1a-01 — host Filament del browse COMGES (SDD-275).
 * UI dominio en Livewire; chrome (acciones header) nativo Filament.
 */
class BrowseComgesCommitments extends Page
{
    protected string $view = 'filament.pages.browse-comges-commitments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Compromisos COMGES';

    protected static ?string $title = 'Compromisos COMGES';

    protected static string|UnitEnum|null $navigationGroup = 'COMGES';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'comges/compromisos';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && FilamentComgesBrowseGate::enabled()
            && ModuleAccess::canAccess('comges');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSubheading(): ?string
    {
        $ss = Auth::user()?->cenHieDependency?->nombre;

        return $ss
            ? 'Seguimiento operativo · '.$ss
            : 'Seguimiento operativo';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ordenar')
                ->label('Ordenar')
                ->url(fn (): string => route('voyager.compromisos.order'))
                ->color('gray')
                ->outlined(),
            Action::make('acciones')
                ->label('Acciones')
                ->url(fn (): string => route('voyager.compromisos.index'))
                ->color('primary'),
            Action::make('agregar')
                ->label('Agregar compromiso')
                ->url(fn (): string => route('voyager.compromisos.create'))
                ->color('success'),
        ];
    }
}
