<?php

namespace App\Filament\Configuracion\Resources\PublicInfoPanels\Pages;

use App\Filament\Configuracion\Resources\PublicInfoPanels\PublicInfoPanelResource;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas\PublicInfoPanelForm;
use App\Filament\Support\Pages\CreateRecord;

class CreatePublicInfoPanel extends CreateRecord
{
    protected static string $resource = PublicInfoPanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PublicInfoPanelForm::agentGuideAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PublicInfoPanelForm::assertPublishable($data);
    }
}
