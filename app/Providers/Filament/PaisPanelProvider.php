<?php

namespace App\Providers\Filament;

use App\Filament\Pages\CentinelaDashboard;
use App\Providers\Filament\Concerns\ConfiguresCentinelaPanel;
use App\Support\Filament\FilamentPanelRegistry;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;

/**
 * Panel país autenticado (SDD-284).
 */
class PaisPanelProvider extends PanelProvider
{
    use ConfiguresCentinelaPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureCentinelaPanel($panel, FilamentPanelRegistry::PAIS)
            ->discoverResources(
                in: app_path('Filament/Pais/Resources'),
                for: 'App\\Filament\\Pais\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Pais/Pages'),
                for: 'App\\Filament\\Pais\\Pages'
            )
            ->pages([
                CentinelaDashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Pais/Widgets'),
                for: 'App\\Filament\\Pais\\Widgets'
            )
            ->widgets([
                AccountWidget::class,
            ]);
    }
}
