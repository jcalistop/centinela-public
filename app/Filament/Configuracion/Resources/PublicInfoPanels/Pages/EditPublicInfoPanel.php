<?php

namespace App\Filament\Configuracion\Resources\PublicInfoPanels\Pages;

use App\Filament\Configuracion\Resources\PublicInfoPanels\PublicInfoPanelResource;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas\PublicInfoPanelForm;
use App\Filament\Support\Pages\EditRecord;
use Filament\Actions\DeleteAction;

class EditPublicInfoPanel extends EditRecord
{
    protected static string $resource = PublicInfoPanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PublicInfoPanelForm::sharePreviewAction(),
            PublicInfoPanelForm::agentGuideAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($this->record->publicado ?? false) === true) {
            $data['slug'] = $this->record->slug;
        }

        return PublicInfoPanelForm::assertPublishable($data);
    }
}
