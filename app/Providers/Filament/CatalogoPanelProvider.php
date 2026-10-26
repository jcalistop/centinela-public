<?php

namespace App\Providers\Filament;

use App\Filament\Pages\CentinelaDashboard;
use App\Providers\Filament\Concerns\ConfiguresCentinelaPanel;
use App\Support\Filament\FilamentPanelRegistry;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;

/**
 * Panel catálogo global (SDD-284).
 */
class CatalogoPanelProvider extends PanelProvider
{
    use ConfiguresCentinelaPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureCentinelaPanel($panel, FilamentPanelRegistry::CATALOGO)
            ->discoverResources(
                in: app_path('Filament/Catalogo/Resources'),
                for: 'App\\Filament\\Catalogo\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Catalogo/Pages'),
                for: 'App\\Filament\\Catalogo\\Pages'
            )
            ->pages([
                CentinelaDashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Catalogo/Widgets'),
                for: 'App\\Filament\\Catalogo\\Widgets'
            )
            ->widgets([
                AccountWidget::class,
            ]);
    }
}
