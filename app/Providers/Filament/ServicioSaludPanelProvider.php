<?php

namespace App\Providers\Filament;

use App\Filament\Pages\CentinelaDashboard;
use App\Models\User;
use App\Providers\Filament\Concerns\ConfiguresCentinelaPanel;
use App\Support\Filament\FilamentPanelRegistry;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;

/**
 * Panel operación SS — default + login (SDD-284).
 */
class ServicioSaludPanelProvider extends PanelProvider
{
    use ConfiguresCentinelaPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureCentinelaPanel($panel, FilamentPanelRegistry::SERVICIO_SALUD)
            ->default()
            ->login()
            ->homeUrl(function (): ?string {
                $user = Auth::user();

                if (! $user instanceof User) {
                    return null;
                }

                return FilamentPanelRegistry::homeUrlForUser($user);
            })
            ->discoverResources(
                in: app_path('Filament/ServicioSalud/Resources'),
                for: 'App\\Filament\\ServicioSalud\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/ServicioSalud/Pages'),
                for: 'App\\Filament\\ServicioSalud\\Pages'
            )
            ->pages([
                CentinelaDashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/ServicioSalud/Widgets'),
                for: 'App\\Filament\\ServicioSalud\\Widgets'
            )
            ->widgets([
                AccountWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => Blade::render('@livewire(\'filament.panel-context-bar\')'),
            );
    }
}
