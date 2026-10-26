<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Pages;

use App\Filament\Catalogo\Resources\CenCenters\CenCenterResource;
use App\Models\CenCenterSyncRun;
use App\Models\CenHieDependency;
use App\Models\User;
use App\Support\Cen\CenCenterNationalSyncDispatcher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class ManageSyncRuns extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CenCenterResource::class;

    protected static ?string $title = 'Historial de sincronización';

    protected static ?string $navigationLabel = 'Historial sync';

    protected string $view = 'filament.resources.cen-centers.pages.manage-sync-runs';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return CenCenterResource::canViewAny()
            && app(CenCenterNationalSyncDispatcher::class)->canSyncServicioSalud($user);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->syncRunsQuery())
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('ss_codigo')->label('SS')->placeholder('Nacional'),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('triggeredBy.name')->label('Usuario')->placeholder('—'),
                TextColumn::make('created_at')->label('Inicio')->dateTime()->sortable(),
                TextColumn::make('finished_at')->label('Fin')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }

    protected function getHeaderActions(): array
    {
        $dispatcher = app(CenCenterNationalSyncDispatcher::class);
        /** @var User $user */
        $user = auth()->user();

        return [
            Action::make('syncSs')
                ->label('Sincronizar SS')
                ->visible(fn (): bool => $dispatcher->canSyncServicioSalud($user))
                ->requiresConfirmation()
                ->action(function () use ($dispatcher, $user): void {
                    try {
                        $message = $dispatcher->dispatchServicioSalud($user);
                        Notification::make()->title($message)->success()->send();
                    } catch (RuntimeException $exception) {
                        Notification::make()->title($exception->getMessage())->warning()->send();
                    }
                }),
            Action::make('syncFull')
                ->label('Sincronizar nacional')
                ->visible(fn (): bool => $dispatcher->canSyncNationalFull($user))
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () use ($dispatcher, $user): void {
                    try {
                        $message = $dispatcher->dispatchNationalFull($user);
                        Notification::make()->title($message)->success()->send();
                    } catch (RuntimeException $exception) {
                        Notification::make()->title($exception->getMessage())->warning()->send();
                    }
                }),
        ];
    }

    private function syncRunsQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();
        $query = CenCenterSyncRun::query()->with('triggeredBy');

        if ($user->hasRole('control-gestion') && ! $user->hasRole('admin')) {
            $ssCodigo = CenHieDependency::query()
                ->whereKey($user->cen_hie_dependency_id)
                ->value('codigo');

            if (is_string($ssCodigo) && $ssCodigo !== '') {
                $query->where('ss_codigo', $ssCodigo);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }
}
