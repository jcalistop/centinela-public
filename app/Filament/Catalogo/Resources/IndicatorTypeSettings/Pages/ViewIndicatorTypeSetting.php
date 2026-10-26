<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages;

use App\Filament\Catalogo\Resources\IndicatorTypeSettings\IndicatorTypeSettingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIndicatorTypeSetting extends ViewRecord
{
    protected static string $resource = IndicatorTypeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
