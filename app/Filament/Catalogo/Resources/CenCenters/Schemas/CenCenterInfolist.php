<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CenCenterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Básicos')
                    ->schema([
                        TextEntry::make('codigo')->label('Código'),
                        TextEntry::make('nombre_oficial')->label('Nombre oficial'),
                        TextEntry::make('nombre_corto')->label('Nombre corto'),
                        TextEntry::make('origen')->label('Origen')->badge(),
                        TextEntry::make('cenHieDependency.nombre')->label('Servicio de Salud'),
                        TextEntry::make('codigo_madre')->label('Código madre')->placeholder('—'),
                        IconEntry::make('tarea_pendiente')->label('Tarea pendiente')->boolean(),
                        TextEntry::make('synced_at')->label('Última sync')->dateTime()->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Clasificación')
                    ->schema([
                        TextEntry::make('cen_type')->label('Tipo')->badge(),
                        TextEntry::make('cen_scope')->label('Ámbito')->badge(),
                        TextEntry::make('cen_adm_dependency')->label('Dep. administrativa'),
                        TextEntry::make('cen_certification')->label('Certificación'),
                        TextEntry::make('pertenencia_snss')->label('Pertenencia SNSS')->badge(),
                        TextEntry::make('nivel_atencion')->label('Nivel atención')->badge(),
                        TextEntry::make('estado_funcionamiento')->label('Estado funcionamiento')->badge(),
                        TextEntry::make('tipo_atencion')->label('Tipo atención')->badge(),
                    ])
                    ->columns(2),
                Section::make('Ubicación')
                    ->schema([
                        TextEntry::make('cenAddress.via')->label('Vía')->placeholder('—'),
                        TextEntry::make('cenAddress.direccion')->label('Dirección')->placeholder('—'),
                        TextEntry::make('cenAddress.numero')->label('Número')->placeholder('—'),
                        TextEntry::make('cenAddress.cenCommune.nombre')->label('Comuna')->placeholder('—'),
                        TextEntry::make('cenAddress.latitud')->label('Latitud')->placeholder('—'),
                        TextEntry::make('cenAddress.longitud')->label('Longitud')->placeholder('—'),
                        TextEntry::make('cenAddress.telefono')->label('Teléfono')->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }
}
