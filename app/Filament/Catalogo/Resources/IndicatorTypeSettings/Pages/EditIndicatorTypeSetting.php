<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages;

use App\Filament\Catalogo\Resources\IndicatorTypeSettings\IndicatorTypeSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIndicatorTypeSetting extends EditRecord
{
    protected static string $resource = IndicatorTypeSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
