<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Pages;

use App\Filament\Catalogo\Resources\CenCenters\CenCenterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCenCenter extends ViewRecord
{
    protected static string $resource = CenCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
