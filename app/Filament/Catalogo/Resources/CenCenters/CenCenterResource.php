<?php

namespace App\Filament\Catalogo\Resources\CenCenters;

use App\Filament\Catalogo\Resources\CenCenters\Pages\CreateCenCenter;
use App\Filament\Catalogo\Resources\CenCenters\Pages\EditCenCenter;
use App\Filament\Catalogo\Resources\CenCenters\Pages\ListCenCenters;
use App\Filament\Catalogo\Resources\CenCenters\Pages\ManageGlosaEquivalences;
use App\Filament\Catalogo\Resources\CenCenters\Pages\ManageSyncRuns;
use App\Filament\Catalogo\Resources\CenCenters\Pages\ViewCenCenter;
use App\Filament\Catalogo\Resources\CenCenters\RelationManagers\NotesRelationManager;
use App\Filament\Catalogo\Resources\CenCenters\Schemas\CenCenterForm;
use App\Filament\Catalogo\Resources\CenCenters\Schemas\CenCenterInfolist;
use App\Filament\Catalogo\Resources\CenCenters\Tables\CenCentersTable;
use App\Models\CenCenter;
use App\Models\User;
use App\Services\Cen\CenCenterBrowseService;
use App\Support\Configuration\FilamentEstablecimientosGate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * T2b — BREAD KEEP establecimientos en Filament (SDD-264). Paridad + flag.
 */
class CenCenterResource extends Resource
{
    protected static ?string $model = CenCenter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Establecimientos';

    protected static ?string $modelLabel = 'establecimiento';

    protected static ?string $pluralModelLabel = 'establecimientos';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'establecimientos';

    protected static ?string $recordTitleAttribute = 'nombre_corto';

    public static function form(Schema $schema): Schema
    {
        return CenCenterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CenCenterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CenCentersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return app(CenCenterBrowseService::class)
            ->scopedCentersQueryForBrowse($user);
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        // Rutas estáticas antes de `/{record}` (si no, "sync-runs"/"glosas" se resuelven como CenCenter).
        return [
            'index' => ListCenCenters::route('/'),
            'create' => CreateCenCenter::route('/create'),
            'sync-runs' => ManageSyncRuns::route('/sync-runs'),
            'glosas' => ManageGlosaEquivalences::route('/glosas'),
            'view' => ViewCenCenter::route('/{record}'),
            'edit' => EditCenCenter::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return FilamentEstablecimientosGate::enabled() && parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        if (! FilamentEstablecimientosGate::enabled() || ! parent::canCreate()) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User
            && ! app(CenCenterBrowseService::class)->isExploringForeignSs($user);
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentEstablecimientosGate::enabled() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentEstablecimientosGate::enabled() && parent::canDelete($record);
    }

    public static function canView(Model $record): bool
    {
        return FilamentEstablecimientosGate::enabled() && parent::canView($record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
