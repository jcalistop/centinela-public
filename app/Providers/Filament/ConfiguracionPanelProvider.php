<?php

namespace App\Providers\Filament;

use App\Filament\Pages\CentinelaDashboard;
use App\Providers\Filament\Concerns\ConfiguresCentinelaPanel;
use App\Support\Filament\FilamentPanelRegistry;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;

/**
 * Panel plataforma (SDD-284). Login vive en servicio-salud (panel default).
 */
class ConfiguracionPanelProvider extends PanelProvider
{
    use ConfiguresCentinelaPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureCentinelaPanel($panel, FilamentPanelRegistry::CONFIGURACION)
            ->discoverResources(
                in: app_path('Filament/Configuracion/Resources'),
                for: 'App\\Filament\\Configuracion\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Configuracion/Pages'),
                for: 'App\\Filament\\Configuracion\\Pages'
            )
            ->pages([
                CentinelaDashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Configuracion/Widgets'),
                for: 'App\\Filament\\Configuracion\\Widgets'
            )
            ->widgets([
                AccountWidget::class,
            ]);
    }
}
