<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypeSettings;

use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages\CreateIndicatorTypeSetting;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages\EditIndicatorTypeSetting;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages\ListIndicatorTypeSettings;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages\ViewIndicatorTypeSetting;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Schemas\IndicatorTypeSettingForm;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Schemas\IndicatorTypeSettingInfolist;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Tables\IndicatorTypeSettingsTable;
use App\Models\IndicatorTypeSetting;
use App\Support\Configuration\FilamentConfigurarTipoIndicadorGate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * T2c — BREAD KEEP configurar-tipo-indicador en Filament (SDD-265). Paridad + flag.
 */
class IndicatorTypeSettingResource extends Resource
{
    protected static ?string $model = IndicatorTypeSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Configurar tipo indicador';

    protected static ?string $modelLabel = 'configuración de tipo indicador';

    protected static ?string $pluralModelLabel = 'configuraciones de tipo indicador';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'configurar-tipo-indicador';

    public static function form(Schema $schema): Schema
    {
        return IndicatorTypeSettingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IndicatorTypeSettingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndicatorTypeSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndicatorTypeSettings::route('/'),
            'create' => CreateIndicatorTypeSetting::route('/create'),
            'view' => ViewIndicatorTypeSetting::route('/{record}'),
            'edit' => EditIndicatorTypeSetting::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return FilamentConfigurarTipoIndicadorGate::enabled() && parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        return FilamentConfigurarTipoIndicadorGate::enabled() && parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentConfigurarTipoIndicadorGate::enabled() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentConfigurarTipoIndicadorGate::enabled() && parent::canDelete($record);
    }

    public static function canView(Model $record): bool
    {
        return FilamentConfigurarTipoIndicadorGate::enabled() && parent::canView($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
