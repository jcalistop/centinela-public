<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypeSettings\Tables;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\IndicatorType\TipoEvaluacion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IndicatorTypeSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('indicatorType.nombre_indicador')
                    ->label('Instrumento')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year.ano')
                    ->label('Año')
                    ->sortable(),
                TextColumn::make('cenHieDependency.nombre')
                    ->label('SS')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('etapa_vigente')
                    ->label('Etapa')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state instanceof EtapaVigente) {
                            return $state->label();
                        }

                        $enum = EtapaVigente::tryFrom((string) $state);

                        return $enum?->label() ?? (string) $state;
                    })
                    ->sortable(),
                TextColumn::make('tipo_evaluacion')
                    ->label('Tipo eval.')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state instanceof TipoEvaluacion) {
                            return $state->value;
                        }

                        return (string) $state;
                    })
                    ->sortable(),
                IconColumn::make('habilitado')
                    ->label('Habilitado')
                    ->boolean(),
                TextColumn::make('panel_minsal')
                    ->label('Panel Minsal')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('etapa_vigente')
                    ->label('Etapa')
                    ->options(collect(EtapaVigente::cases())->mapWithKeys(
                        fn (EtapaVigente $case): array => [$case->value => $case->label()]
                    )->all()),
                SelectFilter::make('habilitado')
                    ->label('Habilitado')
                    ->options([
                        '1' => 'Sí',
                        '0' => 'No',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
