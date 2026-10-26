<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages;

use App\Filament\Catalogo\Resources\IndicatorTypeSettings\IndicatorTypeSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndicatorTypeSettings extends ListRecords
{
    protected static string $resource = IndicatorTypeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
