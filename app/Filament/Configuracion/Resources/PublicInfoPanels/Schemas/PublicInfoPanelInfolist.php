<?php

namespace App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas;

use App\Models\PublicInfoPanel;
use App\Support\Dashboard\PublicInfoPanelPreview;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PublicInfoPanelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $isBundle = fn (?PublicInfoPanel $record): bool => $record instanceof PublicInfoPanel
            && $record->isHtmlBundle();
        $isEmbed = fn (?PublicInfoPanel $record): bool => $record instanceof PublicInfoPanel
            && $record->isEmbedExterno();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Resumen')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('tipo')
                            ->label('Tipo')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                PublicInfoPanel::TIPO_EMBED_EXTERNO => 'Embed',
                                default => 'HTML',
                            }),
                        IconEntry::make('publicado')
                            ->label('Publicado')
                            ->boolean(),
                        TextEntry::make('version_panel')
                            ->label('Versión')
                            ->placeholder('—'),
                        TextEntry::make('slug')
                            ->label('Slug')
                            ->copyable(),
                        TextEntry::make('descripcion')
                            ->label('Descripción')
                            ->columnSpanFull(),
                    ])
                    ->columns(4),
                Section::make('Autoría')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('autor')
                            ->label('Autor')
                            ->placeholder('—'),
                        TextEntry::make('autor_email')
                            ->label('Correo')
                            ->placeholder('—'),
                        TextEntry::make('institucion')
                            ->label('Institución')
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(fn (?PublicInfoPanel $record): bool => ! filled($record?->autor)
                        && ! filled($record?->autor_email)
                        && ! filled($record?->institucion)),
                Section::make('Origen')
                    ->columnSpanFull()
                    ->description('Archivos curados o URL de embed. La miniatura es la del hub público.')
                    ->schema([
                        TextEntry::make('embed_url')
                            ->label('URL de embed')
                            ->placeholder('—')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null, shouldOpenInNewTab: true)
                            ->visible($isEmbed)
                            ->columnSpanFull(),
                        TextEntry::make('html_path')
                            ->label('HTML')
                            ->placeholder('—')
                            ->copyable()
                            ->visible($isBundle),
                        TextEntry::make('csv_path')
                            ->label('CSV')
                            ->placeholder('—')
                            ->copyable()
                            ->visible($isBundle),
                        ImageEntry::make('preview_png_path')
                            ->label('Miniatura')
                            ->visible(fn (?PublicInfoPanel $record): bool => filled($record?->preview_png_path))
                            ->getStateUsing(fn (?PublicInfoPanel $record): ?string => self::previewImageUrl($record))
                            ->imageHeight(80),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Vista previa')
                    ->columnSpanFull()
                    ->description(fn (?PublicInfoPanel $record): string => match (true) {
                        ! filled($record?->frameSrc()) => 'Cuando el panel tenga HTML o embed, aquí se muestra igual que en el dashboard público.',
                        (bool) ($record?->publicado) => 'Así se ve el panel publicado.',
                        default => 'Revisión — el panel aún no está en el dashboard público.',
                    })
                    ->schema([
                        View::make('filament.public-info-panels.preview-frame'),
                    ]),
            ]);
    }

    private static function previewImageUrl(?PublicInfoPanel $record): ?string
    {
        if (! $record instanceof PublicInfoPanel || ! filled($record->preview_png_path)) {
            return null;
        }

        if ($record->publicado) {
            return $record->previewUrl();
        }

        return PublicInfoPanelPreview::signedTypedUrl($record->slug, 'preview');
    }
}
