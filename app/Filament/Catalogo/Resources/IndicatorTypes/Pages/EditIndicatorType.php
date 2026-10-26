<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypes\Pages;

use App\Filament\Catalogo\Resources\IndicatorTypes\IndicatorTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIndicatorType extends EditRecord
{
    protected static string $resource = IndicatorTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['view'] = null;
        unset($data['route']);

        return $data;
    }
}
