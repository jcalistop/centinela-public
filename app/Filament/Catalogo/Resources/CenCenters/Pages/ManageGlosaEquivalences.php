<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Pages;

use App\Filament\Catalogo\Resources\CenCenters\CenCenterResource;
use App\Models\CenCatalogGlosaEquivalence;
use App\Models\User;
use App\Services\Cen\CenCatalogGlosaEquivalenceExportService;
use App\Services\Cen\CenCatalogGlosaEquivalenceService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManageGlosaEquivalences extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CenCenterResource::class;

    protected static ?string $title = 'Equivalencias de glosa';

    protected string $view = 'filament.resources.cen-centers.pages.manage-glosa-equivalences';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasRole('admin')
            && CenCenterResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CenCatalogGlosaEquivalence::query()
                    ->where('active', true)
                    ->latest('id')
            )
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('field_key')->label('Campo')->searchable(),
                TextColumn::make('source_glosa')->label('Glosa cruda')->searchable()->limit(40),
                TextColumn::make('target_value')->label('Canónico')->limit(40),
                TextColumn::make('created_at')->label('Creada')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar equivalencia')
                    ->form([
                        TextInput::make('field_key')->label('Campo')->required(),
                        TextInput::make('source_glosa')->label('Glosa cruda')->required(),
                        TextInput::make('target_value')->label('Valor canónico')->required(),
                    ])
                    ->using(function (array $data): CenCatalogGlosaEquivalence {
                        /** @var User $user */
                        $user = auth()->user();

                        return app(CenCatalogGlosaEquivalenceService::class)->register(
                            $data['field_key'],
                            $data['source_glosa'],
                            $data['target_value'],
                            $user,
                        );
                    }),
                Action::make('export')
                    ->label('Exportar activas')
                    ->action(function (): BinaryFileResponse {
                        return app(CenCatalogGlosaEquivalenceExportService::class)->downloadActive();
                    }),
                Action::make('deactivate')
                    ->label('Desactivar por ID')
                    ->form([
                        Select::make('id')
                            ->label('Equivalencia')
                            ->options(
                                CenCatalogGlosaEquivalence::query()
                                    ->where('active', true)
                                    ->orderByDesc('id')
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(fn (CenCatalogGlosaEquivalence $row): array => [
                                        $row->id => "#{$row->id} {$row->field_key}: {$row->source_glosa}",
                                    ])
                                    ->all()
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $row = CenCatalogGlosaEquivalence::query()->findOrFail($data['id']);
                        app(CenCatalogGlosaEquivalenceService::class)->deactivate($row);
                        Notification::make()->title('Equivalencia desactivada')->success()->send();
                    }),
            ]);
    }
}
