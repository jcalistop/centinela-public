<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Pages;

use App\Filament\Catalogo\Resources\CenCenters\CenCenterResource;
use App\Models\User;
use App\Services\Cen\CenCenterBrowseService;
use App\Services\Cen\CenCenterExcelExportService;
use App\Support\Cen\CenCenterNationalSyncDispatcher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListCenCenters extends ListRecords
{
    protected static string $resource = CenCenterResource::class;

    public function mount(): void
    {
        parent::mount();

        $user = auth()->user();
        if ($user instanceof User) {
            app(CenCenterBrowseService::class)->applyRequestedSs($user, request()->query('ss'));
        }
    }

    protected function getHeaderActions(): array
    {
        $dispatcher = app(CenCenterNationalSyncDispatcher::class);
        $browse = app(CenCenterBrowseService::class);
        $user = auth()->user();
        assert($user instanceof User);

        $exploring = $browse->isExploringForeignSs($user);
        $ssOptions = collect($browse->servicioOptions())
            ->mapWithKeys(fn (array $option): array => [$option['id'] => $option['nombre']])
            ->all();

        return [
            Action::make('browseSs')
                ->label('Servicio de Salud')
                ->form([
                    Select::make('ss')
                        ->label('Servicio de Salud')
                        ->options($ssOptions)
                        ->default($browse->effectiveBrowseSsId($user))
                        ->required(),
                ])
                ->action(function (array $data) use ($user, $browse): void {
                    $browse->applyRequestedSs($user, $data['ss'] ?? null);
                }),
            CreateAction::make()
                ->visible(fn (): bool => ! $exploring),
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->action(function () use ($user): BinaryFileResponse {
                    return app(CenCenterExcelExportService::class)->downloadForUser($user, 'hieFilter');
                }),
            Action::make('syncSs')
                ->label('Sincronizar SS')
                ->visible(fn (): bool => ! $exploring && $dispatcher->canSyncServicioSalud($user))
                ->requiresConfirmation()
                ->action(function () use ($dispatcher, $user): void {
                    try {
                        Notification::make()->title($dispatcher->dispatchServicioSalud($user))->success()->send();
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
                        Notification::make()->title($dispatcher->dispatchNationalFull($user))->success()->send();
                    } catch (RuntimeException $exception) {
                        Notification::make()->title($exception->getMessage())->warning()->send();
                    }
                }),
            Action::make('syncHistory')
                ->label('Historial sync')
                ->url(CenCenterResource::getUrl('sync-runs'))
                ->visible(fn (): bool => $dispatcher->canSyncServicioSalud($user)),
            Action::make('glosas')
                ->label('Glosas')
                ->url(CenCenterResource::getUrl('glosas'))
                ->visible(fn (): bool => $user->hasRole('admin')),
        ];
    }
}
