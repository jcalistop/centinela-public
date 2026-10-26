<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypes\Pages;

use App\Filament\Catalogo\Resources\IndicatorTypes\IndicatorTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndicatorTypes extends ListRecords
{
    protected static string $resource = IndicatorTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
