<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Pages;

use App\Enums\Cen\CenCenterOrigen;
use App\Filament\Catalogo\Resources\CenCenters\CenCenterResource;
use App\Filament\Catalogo\Resources\CenCenters\Concerns\SavesCenCenterAddress;
use App\Models\CenCenter;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCenCenter extends EditRecord
{
    use SavesCenCenterAddress;

    protected static string $resource = CenCenterResource::class;

    /** @var array<string, mixed> */
    private array $pendingAddress = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        if ($record instanceof CenCenter && $record->origen === CenCenterOrigen::Api) {
            return false;
        }

        return parent::canAccess($parameters);
    }

    /**
     * @param  Model|CenCenter  $record
     */
    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);

        if ($record instanceof CenCenter && $record->origen === CenCenterOrigen::Api) {
            abort(403, 'Los datos oficiales de establecimientos API no se editan manualmente. Use sincronización o bitácora.');
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var CenCenter $record */
        $record = $this->getRecord();

        return $this->mergeAddressIntoFormData($record, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingAddress = $this->extractAddressFormData($data);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var CenCenter $center */
        $center = $this->getRecord();
        $this->persistCenAddress($center, $this->pendingAddress);
    }
}
