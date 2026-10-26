<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypes\Pages;

use App\Filament\Catalogo\Resources\IndicatorTypes\IndicatorTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIndicatorType extends CreateRecord
{
    protected static string $resource = IndicatorTypeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['view'] = null;

        return $data;
    }
}
