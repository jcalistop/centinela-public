<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Pages;

use App\Enums\Cen\CenCenterOrigen;
use App\Filament\Catalogo\Resources\CenCenters\CenCenterResource;
use App\Filament\Catalogo\Resources\CenCenters\Concerns\SavesCenCenterAddress;
use App\Models\CenCenter;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCenCenter extends CreateRecord
{
    use SavesCenCenterAddress;

    protected static string $resource = CenCenterResource::class;

    /** @var array<string, mixed> */
    private array $pendingAddress = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['origen'] = CenCenterOrigen::Manual->value;
        $this->pendingAddress = $this->extractAddressFormData($data);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var CenCenter $center */
        $center = parent::handleRecordCreation($data);
        $this->persistCenAddress($center, $this->pendingAddress);

        return $center;
    }
}
