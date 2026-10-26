<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Home de cada panel Filament (SDD-284): aviso dual-run + link a Voyager.
 */
class CentinelaDashboard extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $title = 'Inicio';

    protected static ?int $navigationSort = -100;

    protected string $view = 'filament.pages.centinela-dashboard';
}
