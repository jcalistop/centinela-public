<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypeSettings\Schemas;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\IndicatorType\TipoEvaluacion;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IndicatorTypeSettingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('indicatorType.nombre_indicador')
                    ->label('Instrumento'),
                TextEntry::make('year.ano')
                    ->label('Año'),
                TextEntry::make('cenHieDependency.nombre')
                    ->label('Servicio de Salud'),
                TextEntry::make('etapa_vigente')
                    ->label('Etapa vigente')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state instanceof EtapaVigente) {
                            return $state->label();
                        }

                        $enum = EtapaVigente::tryFrom((string) $state);

                        return $enum?->label() ?? (string) $state;
                    }),
                TextEntry::make('tipo_evaluacion')
                    ->label('Tipo evaluación')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state instanceof TipoEvaluacion) {
                            return $state->value;
                        }

                        return (string) $state;
                    }),
                IconEntry::make('habilitado')
                    ->label('Habilitado')
                    ->boolean(),
                TextEntry::make('panel_minsal')
                    ->label('Panel Minsal')
                    ->placeholder('—')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true),
                TextEntry::make('texto_enriquecido')
                    ->label('Texto enriquecido')
                    ->html()
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }
}
