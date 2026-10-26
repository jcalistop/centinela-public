<?php

namespace App\Filament\Configuracion\Resources\PublicInfoPanels\Pages;

use App\Filament\Configuracion\Resources\PublicInfoPanels\PublicInfoPanelResource;
use App\Support\Dashboard\PublicInfoPanelInventory;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;

class ListPublicInfoPanels extends ListRecords
{
    protected static string $resource = PublicInfoPanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inventory')
                ->label('Copiar inventario')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->modalHeading('Inventario de paneles públicos')
                ->modalDescription('Fotografía de metadatos para un agente externo. No sustituye el contrato para agentes (SDD-291).')
                ->modalWidth(Width::ThreeExtraLarge)
                ->modalContent(fn (): View => $this->inventoryModal())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
            CreateAction::make(),
        ];
    }

    private function inventoryModal(): View
    {
        $panels = PublicInfoPanelInventory::panels();

        return view('filament.public-info-panels.inventory-modal', [
            'markdown' => PublicInfoPanelInventory::markdown($panels),
            'json' => PublicInfoPanelInventory::toJson($panels),
            'total' => $panels->count(),
        ]);
    }
}
