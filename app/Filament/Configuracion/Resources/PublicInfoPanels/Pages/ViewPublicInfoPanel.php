<?php

namespace App\Filament\Configuracion\Resources\PublicInfoPanels\Pages;

use App\Filament\Configuracion\Resources\PublicInfoPanels\PublicInfoPanelResource;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas\PublicInfoPanelForm;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPublicInfoPanel extends ViewRecord
{
    protected static string $resource = PublicInfoPanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PublicInfoPanelForm::sharePreviewAction(),
            PublicInfoPanelForm::agentGuideAction(),
            EditAction::make(),
        ];
    }
}
