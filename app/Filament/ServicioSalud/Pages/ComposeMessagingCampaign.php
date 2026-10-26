<?php

namespace App\Filament\ServicioSalud\Pages;

use App\Filament\Messaging\Concerns\ComposesMessagingCampaign;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ComposeMessagingCampaign extends Page implements HasTable
{
    use ComposesMessagingCampaign;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Mensajería';

    protected static ?string $title = 'Enviar mensajería';

    protected static string|UnitEnum|null $navigationGroup = 'Comunicaciones';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'mensajeria';

    protected static function actorIsAdminPanel(): bool
    {
        return false;
    }
}
