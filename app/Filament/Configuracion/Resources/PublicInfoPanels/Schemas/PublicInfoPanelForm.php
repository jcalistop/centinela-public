<?php

namespace App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas;

use App\Models\PublicInfoPanel;
use App\Support\Dashboard\PublicInfoPanelAgentGuide;
use App\Support\Dashboard\PublicInfoPanelEmbedUrl;
use App\Support\Dashboard\PublicInfoPanelPreview;
use App\Support\Dashboard\PublicInfoPanelPublishGuard;
use App\Support\Dashboard\PublicInfoPanelShare;
use App\Support\Dashboard\PublicInfoPanelZipPreloader;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PublicInfoPanelForm
{
    public static function configure(Schema $schema): Schema
    {
        $disk = config('voyager.storage.disk', 'private');
        $isBundle = fn (Get $get): bool => ($get('tipo') ?: PublicInfoPanel::TIPO_HTML_BUNDLE) === PublicInfoPanel::TIPO_HTML_BUNDLE;
        $isEmbed = fn (Get $get): bool => $get('tipo') === PublicInfoPanel::TIPO_EMBED_EXTERNO;

        return $schema
            ->columns(1)
            ->components([
                Section::make('Resumen')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                if (filled($get('slug'))) {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            })
                            ->columnSpanFull(),
                        Select::make('tipo')
                            ->label('Tipo de panel')
                            ->options([
                                PublicInfoPanel::TIPO_HTML_BUNDLE => 'Paquete HTML + CSV + PNG',
                                PublicInfoPanel::TIPO_EMBED_EXTERNO => 'Embed externo (URL pública)',
                            ])
                            ->required()
                            ->default(PublicInfoPanel::TIPO_HTML_BUNDLE)
                            ->live()
                            ->helperText('El embed externo solo acepta hosts allowlist (Power BI, Tableau, Looker Studio, etc.).'),
                        Toggle::make('publicado')
                            ->label('Publicado')
                            ->default(false)
                            ->live()
                            ->helperText(fn (Get $get): string => $isEmbed($get)
                                ? 'Requiere URL de embed allowlist + PNG de preview.'
                                : 'Requiere HTML, CSV y PNG de preview.'),
                        TextInput::make('version_panel')
                            ->label('Versión del panel')
                            ->maxLength(64)
                            ->helperText('Texto libre (p. ej. 1.2 o 2026-10).'),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?PublicInfoPanel $record): bool => (bool) ($record?->publicado))
                            ->dehydrated()
                            ->helperText('Usado en /publico/paneles/{slug}. Editable solo si el panel no está publicado.'),
                        Textarea::make('descripcion')
                            ->label('Descripción')
                            ->required()
                            ->rows(3)
                            ->maxLength(400)
                            ->columnSpanFull(),
                        Hidden::make('orden')->default(0),
                    ])
                    ->columns(4),
                Section::make('Autoría')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('autor')
                            ->label('Autor')
                            ->maxLength(255),
                        TextInput::make('autor_email')
                            ->label('Correo del autor')
                            ->email()
                            ->rules(['nullable', 'email:rfc'])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (TextInput $component, mixed $state, mixed $livewire): void {
                                if (! filled($state)) {
                                    return;
                                }

                                $livewire->validateOnly($component->getStatePath());
                            })
                            ->maxLength(255)
                            ->helperText('Se valida el formato del correo al salir del campo (opcional).'),
                        TextInput::make('institucion')
                            ->label('Institución')
                            ->maxLength(255),
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
                        FileUpload::make('zip_precarga')
                            ->label('Precargar desde ZIP')
                            ->helperText('Opcional. Contrato: manifest.json + preview.png (+ panel.html/datos.csv o embed_url). No publica al subir; revisa y guarda.')
                            ->acceptedFileTypes([
                                'application/zip',
                                'application/x-zip-compressed',
                                'application/octet-stream',
                            ])
                            ->maxSize(56320)
                            ->storeFiles(false)
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                self::applyZipPreload($state, $set);
                            }),
                        TextInput::make('embed_url')
                            ->label('URL de embed')
                            ->url()
                            ->maxLength(2048)
                            ->visible($isEmbed)
                            ->live(onBlur: true)
                            ->required(fn (Get $get): bool => $isEmbed($get) && (bool) $get('publicado'))
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    if ($get('tipo') !== PublicInfoPanel::TIPO_EMBED_EXTERNO) {
                                        return;
                                    }
                                    if (! filled($value)) {
                                        return;
                                    }
                                    if (! PublicInfoPanelEmbedUrl::isAllowed(is_string($value) ? $value : null)) {
                                        $fail('La URL debe ser https y pertenecer a un host allowlist (Power BI, Tableau, Looker Studio, Datawrapper, Flourish o ArcGIS público).');
                                    }
                                },
                            ])
                            ->afterStateUpdated(function (TextInput $component, mixed $state, mixed $livewire): void {
                                if (! filled($state)) {
                                    return;
                                }

                                $livewire->validateOnly($component->getStatePath());
                            })
                            ->helperText('Solo https en hosts permitidos. Se valida al salir del campo. El contenido debe ser públicamente accesible en el origen.')
                            ->columnSpanFull(),
                        FileUpload::make('html_path')
                            ->label('HTML')
                            ->disk($disk)
                            ->directory(fn (callable $get): string => 'public-info-panels/'.(filled($get('slug')) ? $get('slug') : 'tmp'))
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/html', 'application/xhtml+xml'])
                            ->maxSize(25600)
                            ->downloadable()
                            ->openable()
                            ->preventFilePathTampering(
                                allowFilePathUsing: fn (string $file): bool => self::isTrustedPreloadedFilePath($file),
                            )
                            ->visible($isBundle)
                            ->helperText('Diseño del panel. Rutas relativas al CSV (p. ej. datos.csv) se resuelven en el guest sin editar el HTML. Sin CDN ni javascript:.'),
                        FileUpload::make('csv_path')
                            ->label('CSV')
                            ->disk($disk)
                            ->directory(fn (callable $get): string => 'public-info-panels/'.(filled($get('slug')) ? $get('slug') : 'tmp'))
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->maxSize(51200)
                            ->downloadable()
                            ->preventFilePathTampering(
                                allowFilePathUsing: fn (string $file): bool => self::isTrustedPreloadedFilePath($file),
                            )
                            ->visible($isBundle)
                            ->helperText('Datos (máx. 50 MB). El guest mapea cualquier *.csv relativo del HTML a este archivo.'),
                        FileUpload::make('preview_png_path')
                            ->label('Preview PNG')
                            ->disk($disk)
                            ->directory(fn (callable $get): string => 'public-info-panels/'.(filled($get('slug')) ? $get('slug') : 'tmp'))
                            ->preserveFilenames()
                            ->image()
                            ->acceptedFileTypes(['image/png'])
                            ->maxSize(10240)
                            ->downloadable()
                            ->openable()
                            ->preventFilePathTampering(
                                allowFilePathUsing: fn (string $file): bool => self::isTrustedPreloadedFilePath($file),
                            )
                            ->helperText('Miniatura del hub guest.'),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Vista previa')
                    ->columnSpanFull()
                    ->description('Muestra el último guardado. Guarda para actualizar el iframe.')
                    ->schema([
                        View::make('filament.public-info-panels.preview-frame'),
                    ]),
            ]);
    }

    /**
     * Paths written by ZIP preload (or legacy zip-preload/) that Create may submit as strings.
     * Exact basename allowlist + no `..` (Filament warns against bare prefix checks).
     */
    public static function isTrustedPreloadedFilePath(string $file): bool
    {
        return PublicInfoPanelPublishGuard::isTrustedPreloadedFilePath($file);
    }

    public static function agentGuideAction(): Action
    {
        return Action::make('agentGuide')
            ->label('Contratos para agentes')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->modalHeading('Contratos para agentes')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalContent(view('filament.public-info-panels.agent-guide-modal'))
            ->modalSubmitActionLabel('Descargar .md')
            ->action(fn () => PublicInfoPanelAgentGuide::downloadResponse());
    }

    /**
     * Compartir panel (SDD-293 / SDD-303 enmienda).
     *
     * Publicado → URL pública del hub. No publicado → URL firmada de revisión (7 días).
     * Visible solo si el panel tiene los assets mínimos para mostrarse.
     */
    public static function sharePreviewAction(): Action
    {
        return Action::make('sharePreview')
            ->label(fn (?PublicInfoPanel $record): string => $record?->publicado ? 'Compartir panel' : 'Compartir revisión')
            ->icon('heroicon-o-share')
            ->color('gray')
            ->visible(fn (?PublicInfoPanel $record): bool => $record !== null
                && $record->hasRequiredAssets())
            ->modalHeading(fn (?PublicInfoPanel $record): string => $record?->publicado
                ? 'Compartir panel publicado'
                : 'Compartir revisión del panel')
            ->modalDescription(fn (?PublicInfoPanel $record): string => $record?->publicado
                ? 'Enlace público del dashboard. Cualquier persona con el enlace puede verlo.'
                : 'Enlace temporal sin login. No aparece en la landing pública.')
            ->fillForm(fn (PublicInfoPanel $record): array => [
                'preview_url' => PublicInfoPanelShare::url($record),
            ])
            ->form([
                TextInput::make('preview_url')
                    ->label(fn (?PublicInfoPanel $record): string => $record?->publicado
                        ? 'URL pública'
                        : 'URL de revisión ('.PublicInfoPanelPreview::ttlDays().' días)')
                    ->disabled()
                    ->dehydrated(false)
                    ->copyable()
                    ->columnSpanFull(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (PublicInfoPanel $record): array => [
                Action::make('openMail')
                    ->label('Abrir correo')
                    ->icon('heroicon-o-envelope')
                    ->url(PublicInfoPanelShare::mailtoHref($record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function applyZipPreload(mixed $state, Set $set): void
    {
        $path = self::resolveUploadedAbsolutePath($state);
        if ($path === null) {
            return;
        }

        try {
            $formState = app(PublicInfoPanelZipPreloader::class)->preloadFromPath($path);
            foreach ($formState as $key => $value) {
                $set($key, $value);
            }
            $set('zip_precarga', null);

            Notification::make()
                ->title('ZIP precargado')
                ->body('Revisa los campos y guarda. El panel no se publicó automáticamente.')
                ->success()
                ->send();
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'ZIP inválido.';
            Notification::make()
                ->title('No se pudo precargar el ZIP')
                ->body((string) $message)
                ->danger()
                ->send();
            $set('zip_precarga', null);
        }
    }

    public static function resolveUploadedAbsolutePath(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = $state[0] ?? null;
        }

        if ($state instanceof TemporaryUploadedFile) {
            $real = $state->getRealPath();

            return is_string($real) && is_file($real) ? $real : null;
        }

        if (is_string($state) && $state !== '' && is_file($state)) {
            return $state;
        }

        return null;
    }

    /**
     * Guarda de publicación para superficies sin formulario (toggle del listado, SDD-303).
     *
     * Reutiliza las mismas reglas que el formulario sobre los atributos persistidos.
     */
    public static function assertPublishableRecord(PublicInfoPanel $record): void
    {
        PublicInfoPanelPublishGuard::assertPublishableRecord($record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function assertPublishable(array $data): array
    {
        return PublicInfoPanelPublishGuard::assertPublishable($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertHtmlClean(array $data): void
    {
        PublicInfoPanelPublishGuard::assertHtmlClean($data);
    }
}
