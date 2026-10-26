<?php

namespace App\Filament\Configuracion\Resources\PublicInfoPanels\Tables;

use App\Filament\Configuracion\Resources\PublicInfoPanels\PublicInfoPanelResource;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas\PublicInfoPanelForm;
use App\Models\PublicInfoPanel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PublicInfoPanelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version_panel')
                    ->label('Versión')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'embed_externo' => 'Embed',
                        default => 'HTML',
                    })
                    ->toggleable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('publicado')
                    ->label('Publicado')
                    ->sortable()
                    ->disabled(fn (PublicInfoPanel $record): bool => ! PublicInfoPanelResource::canEdit($record))
                    ->updateStateUsing(fn (PublicInfoPanel $record, mixed $state): mixed => self::updatePublishedState($record, (bool) $state)),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                PublicInfoPanelForm::sharePreviewAction()
                    ->iconButton()
                    ->tooltip(fn (?PublicInfoPanel $record): string => $record?->publicado ? 'Compartir panel' : 'Compartir revisión'),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Publicar exige los mismos requisitos que el formulario; despublicar es libre (SDD-303).
     */
    private static function updatePublishedState(PublicInfoPanel $record, bool $publicado): mixed
    {
        if ($publicado) {
            try {
                PublicInfoPanelForm::assertPublishableRecord($record);
            } catch (ValidationException $exception) {
                $message = (string) (collect($exception->errors())->flatten()->first() ?: 'Requisitos de publicación incompletos.');

                Notification::make()
                    ->title('No se pudo publicar el panel')
                    ->body($message)
                    ->danger()
                    ->send();

                return ['error' => $message];
            }
        }

        $record->forceFill(['publicado' => $publicado])->save();

        Notification::make()
            ->title($publicado ? 'Panel publicado' : 'Panel despublicado')
            ->success()
            ->send();

        return $publicado;
    }
}
