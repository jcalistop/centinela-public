<?php

namespace App\Filament\ServicioSalud\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI strip del browse COMGES (SDD-275). No se descubre en el Dashboard.
 */
class ComgesBrowseStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    public int $commitmentsCount = 0;

    public int $alertsCount = 0;

    public string $corteLabel = '—';

    public int $verificadorCount = 0;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make('Compromisos', (string) $this->commitmentsCount)
                ->description('del año activo'),
            Stat::make('Con alerta', (string) $this->alertsCount)
                ->description('excepciones abiertas')
                ->color($this->alertsCount > 0 ? 'warning' : 'gray'),
            Stat::make('Corte vigente', $this->corteLabel)
                ->description('edición / etapa'),
            Stat::make('Verificador pendiente', (string) $this->verificadorCount)
                ->description('medios de verificación')
                ->color($this->verificadorCount > 0 ? 'danger' : 'gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
