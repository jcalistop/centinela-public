<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypes;

use App\Filament\Catalogo\Resources\IndicatorTypes\Pages\CreateIndicatorType;
use App\Filament\Catalogo\Resources\IndicatorTypes\Pages\EditIndicatorType;
use App\Filament\Catalogo\Resources\IndicatorTypes\Pages\ListIndicatorTypes;
use App\Filament\Catalogo\Resources\IndicatorTypes\Schemas\IndicatorTypeForm;
use App\Filament\Catalogo\Resources\IndicatorTypes\Tables\IndicatorTypesTable;
use App\Models\IndicatorType;
use App\Support\Configuration\FilamentInstrumentosEvaluacionGate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * T2a — BREAD KEEP instrumentos-evaluacion en Filament (SDD-262). Paridad + flag.
 */
class IndicatorTypeResource extends Resource
{
    protected static ?string $model = IndicatorType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Instrumentos de evaluación';

    protected static ?string $modelLabel = 'instrumento de evaluación';

    protected static ?string $pluralModelLabel = 'instrumentos de evaluación';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'instrumentos-evaluacion';

    /**
     * Alinear con {@see IndicatorType::getRouteKeyName()} (`route`).
     * Si se fuerza `id` aquí, create/edit generan URL con getRouteKey() (= route)
     * y luego resuelven por id → ModelNotFoundException tras guardar.
     */
    public static function getRecordRouteKeyName(): ?string
    {
        return 'route';
    }

    public static function form(Schema $schema): Schema
    {
        return IndicatorTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndicatorTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndicatorTypes::route('/'),
            'create' => CreateIndicatorType::route('/create'),
            'edit' => EditIndicatorType::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return FilamentInstrumentosEvaluacionGate::enabled() && parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        return FilamentInstrumentosEvaluacionGate::enabled() && parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentInstrumentosEvaluacionGate::enabled() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentInstrumentosEvaluacionGate::enabled() && parent::canDelete($record);
    }

    public static function canView(Model $record): bool
    {
        return FilamentInstrumentosEvaluacionGate::enabled() && parent::canView($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
