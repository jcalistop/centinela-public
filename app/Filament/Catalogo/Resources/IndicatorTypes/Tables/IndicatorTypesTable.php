<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypes\Tables;

use App\Models\IndicatorType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IndicatorTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden', 'asc')
            ->columns([
                TextColumn::make('orden')
                    ->label('Orden')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('clave_indicador')
                    ->label('Clave')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nombre_indicador')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('route')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('icon_class')
                    ->label('Icono (clase)')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('measurement_granularity')
                    ->label('Granularidad')
                    ->formatStateUsing(
                        fn (?string $state): string => IndicatorType::measurementGranularityLabel($state)
                    )
                    ->wrap(),
                IconColumn::make('estado')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
