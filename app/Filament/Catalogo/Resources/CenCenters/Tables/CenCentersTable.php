<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Tables;

use App\Models\User;
use App\Services\Cen\CenCenterBrowseService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CenCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre_corto')
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nombre_corto')
                    ->label('Establecimiento')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cen_type')
                    ->label('Tipo')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('cen_adm_dependency')
                    ->label('Dep. administrativa')
                    ->toggleable(),
                TextColumn::make('cenHieDependency.nombre')
                    ->label('SS')
                    ->toggleable(),
                TextColumn::make('cenAddress.cenCommune.nombre')
                    ->label('Comuna')
                    ->toggleable(),
                TextColumn::make('origen')
                    ->label('Origen')
                    ->badge(),
                IconColumn::make('tarea_pendiente')
                    ->label('Pendiente')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('origen')
                    ->options([
                        'api' => 'API',
                        'manual' => 'Manual',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(function (): bool {
                            $user = auth()->user();

                            return $user instanceof User
                                && ! app(CenCenterBrowseService::class)->isExploringForeignSs($user);
                        }),
                ]),
            ]);
    }
}
