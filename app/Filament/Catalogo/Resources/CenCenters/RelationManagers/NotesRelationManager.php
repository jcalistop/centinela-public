<?php

namespace App\Filament\Catalogo\Resources\CenCenters\RelationManagers;

use App\Models\CenCenter;
use App\Models\User;
use App\Services\Cen\CenCenterBitacoraService;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Bitácora';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Nota')
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('tarea_pendiente')
                    ->label('Marcar tarea pendiente')
                    ->default(fn (): bool => (bool) $this->getOwnerRecord()->tarea_pendiente),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('body')
                    ->label('Contenido')
                    ->wrap()
                    ->limit(120),
                TextColumn::make('user.name')
                    ->label('Autor')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar nota')
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
                    ->createAnother(false)
                    ->using(function (array $data): Model {
                        /** @var CenCenter $center */
                        $center = $this->getOwnerRecord();
                        $user = auth()->user();
                        assert($user instanceof User);

                        return app(CenCenterBitacoraService::class)->storeNote(
                            $center,
                            $user,
                            $data['body'] ?? null,
                            (bool) ($data['tarea_pendiente'] ?? false),
                        );
                    }),
            ]);
    }
}
