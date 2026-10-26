<?php

namespace App\Filament\Configuracion\Resources\PublicInfoPanels;

use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\CreatePublicInfoPanel;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\EditPublicInfoPanel;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\ListPublicInfoPanels;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\ViewPublicInfoPanel;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas\PublicInfoPanelForm;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas\PublicInfoPanelInfolist;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Tables\PublicInfoPanelsTable;
use App\Models\PublicInfoPanel;
use App\Models\User;
use App\Support\Configuration\FilamentSettingsGate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * SDD-282 / SDD-284 enmienda — catálogo de paneles nacionales (HTML + CSV + PNG).
 * Vive en panel `configuracion`; solo rol admin (+ flag settings).
 */
class PublicInfoPanelResource extends Resource
{
    protected static ?string $model = PublicInfoPanel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = 'Paneles públicos';

    protected static ?string $modelLabel = 'panel público';

    protected static ?string $pluralModelLabel = 'paneles públicos';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'paneles-publicos';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        return PublicInfoPanelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PublicInfoPanelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicInfoPanelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicInfoPanels::route('/'),
            'create' => CreatePublicInfoPanel::route('/create'),
            'view' => ViewPublicInfoPanel::route('/{record}'),
            'edit' => EditPublicInfoPanel::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::authorizedAdmin() && parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::authorizedAdmin() && parent::canCreate();
    }

    public static function canView(Model $record): bool
    {
        return static::authorizedAdmin() && parent::canView($record);
    }

    public static function canEdit(Model $record): bool
    {
        return static::authorizedAdmin() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::authorizedAdmin() && parent::canDelete($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    private static function authorizedAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->hasRole('admin')
            && FilamentSettingsGate::enabled();
    }
}
