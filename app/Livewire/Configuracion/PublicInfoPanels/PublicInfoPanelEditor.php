<?php

namespace App\Livewire\Configuracion\PublicInfoPanels;

use App\Livewire\Configuracion\PublicInfoPanels\Concerns\InteractsWithPublicInfoPanelModals;
use App\Models\PublicInfoPanel;
use App\Support\Dashboard\PublicInfoPanelEmbedUrl;
use App\Support\Dashboard\PublicInfoPanelHtmlScanner;
use App\Support\Dashboard\PublicInfoPanelPublishGuard;
use App\Support\Dashboard\PublicInfoPanelZipPreloader;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Crear / editar un panel público en Voyager (SDD-306). Paridad con PublicInfoPanelForm (Filament).
 */
class PublicInfoPanelEditor extends Component
{
    use InteractsWithPublicInfoPanelModals;
    use WithFileUploads;

    /** Campos que puede escribir la precarga ZIP (whitelist). */
    private const ZIP_STATE_FIELDS = [
        'titulo', 'descripcion', 'tipo', 'publicado', 'slug', 'autor', 'autor_email',
        'institucion', 'version_panel', 'embed_url', 'html_path', 'csv_path', 'preview_png_path',
    ];

    private const PATH_FIELDS = ['html_path', 'csv_path', 'preview_png_path'];

    #[Locked]
    public ?int $panelId = null;

    public string $titulo = '';

    public string $tipo = PublicInfoPanel::TIPO_HTML_BUNDLE;

    public bool $publicado = false;

    public ?string $version_panel = null;

    public string $slug = '';

    public string $descripcion = '';

    #[Locked]
    public int $orden = 0;

    public ?string $autor = null;

    public ?string $autor_email = null;

    public ?string $institucion = null;

    public ?string $embed_url = null;

    /** Paths persistidos: solo el servidor los escribe (nunca el cliente). */
    #[Locked]
    public ?string $html_path = null;

    #[Locked]
    public ?string $csv_path = null;

    #[Locked]
    public ?string $preview_png_path = null;

    /** @var TemporaryUploadedFile|null */
    public $zip_precarga = null;

    /** @var TemporaryUploadedFile|null */
    public $htmlUpload = null;

    /** @var TemporaryUploadedFile|null */
    public $csvUpload = null;

    /** @var TemporaryUploadedFile|null */
    public $previewUpload = null;

    public function mount(?PublicInfoPanel $panel = null): void
    {
        $this->requireAdmin();

        if ($panel === null || ! $panel->exists) {
            return;
        }

        $this->panelId = $panel->getKey();
        $this->titulo = (string) $panel->titulo;
        $this->tipo = $panel->tipo ?: PublicInfoPanel::TIPO_HTML_BUNDLE;
        $this->publicado = (bool) $panel->publicado;
        $this->version_panel = $panel->version_panel;
        $this->slug = (string) $panel->slug;
        $this->descripcion = (string) $panel->descripcion;
        $this->orden = (int) $panel->orden;
        $this->autor = $panel->autor;
        $this->autor_email = $panel->autor_email;
        $this->institucion = $panel->institucion;
        $this->embed_url = $panel->embed_url;
        $this->html_path = $panel->html_path;
        $this->csv_path = $panel->csv_path;
        $this->preview_png_path = $panel->preview_png_path;
    }

    public function updatedTitulo(): void
    {
        if (trim($this->slug) === '' && ! $this->isRecordPublished()) {
            $this->slug = Str::slug($this->titulo);
        }
    }

    public function updatedZipPrecarga(): void
    {
        $this->preloadZip();
    }

    public function preloadZip(): void
    {
        $this->requireAdmin();

        $upload = $this->zip_precarga;
        if (! $upload instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $this->validateOnly('zip_precarga');

            $path = $upload->getRealPath();
            if (! is_string($path) || ! is_file($path)) {
                throw ValidationException::withMessages(['zip_precarga' => 'No se pudo leer el archivo ZIP.']);
            }

            $state = app(PublicInfoPanelZipPreloader::class)->preloadFromPath($path);

            foreach (self::PATH_FIELDS as $field) {
                $value = $state[$field] ?? null;
                if (filled($value) && ! PublicInfoPanelPublishGuard::isTrustedPreloadedFilePath((string) $value)) {
                    throw ValidationException::withMessages(['zip_precarga' => 'El ZIP produjo una ruta de archivo no permitida.']);
                }
            }
        } catch (ValidationException $exception) {
            $this->zip_precarga = null;
            $this->resetErrorBag('zip_precarga');
            $message = (string) (collect($exception->errors())->flatten()->first() ?: 'ZIP inválido.');
            $this->dispatch('toast', type: 'danger', message: 'No se pudo precargar el ZIP: '.$message);

            return;
        }

        foreach (self::ZIP_STATE_FIELDS as $field) {
            if (array_key_exists($field, $state)) {
                $this->{$field} = $state[$field];
            }
        }

        $this->publicado = false;
        $this->zip_precarga = null;
        $this->resetValidation();

        $this->dispatch('toast', type: 'success', message: 'ZIP precargado. Revisa los campos y guarda; el panel no se publicó automáticamente.');
    }

    public function save(): void
    {
        $this->requireAdmin();

        $record = $this->record();
        if ($record !== null && $record->publicado) {
            $this->slug = (string) $record->slug;
        }

        $this->validate();

        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        $dir = 'public-info-panels/'.$this->slug;

        try {
            if ($this->tipo === PublicInfoPanel::TIPO_HTML_BUNDLE && $this->htmlUpload instanceof TemporaryUploadedFile) {
                app(PublicInfoPanelHtmlScanner::class)->assertClean((string) $this->htmlUpload->get());
            }

            $paths = [
                'html_path' => $this->html_path,
                'csv_path' => $this->csv_path,
                'preview_png_path' => $this->preview_png_path,
            ];
            $stored = [];
            foreach (['html_path' => $this->htmlUpload, 'csv_path' => $this->csvUpload, 'preview_png_path' => $this->previewUpload] as $field => $upload) {
                if ($upload instanceof TemporaryUploadedFile) {
                    $stored[$field] = $dir.'/'.$this->safeFilename($upload->getClientOriginalName(), $field);
                }
            }

            $candidate = $this->persistableAttributes($paths, $stored);
            PublicInfoPanelPublishGuard::assertPublishable($candidate + $this->assertableAssets($candidate));
        } catch (ValidationException $exception) {
            $this->reportGuardFailure($exception);

            return;
        }

        foreach (['html_path' => $this->htmlUpload, 'csv_path' => $this->csvUpload, 'preview_png_path' => $this->previewUpload] as $field => $upload) {
            if ($upload instanceof TemporaryUploadedFile) {
                $name = $this->safeFilename($upload->getClientOriginalName(), $field);
                $disk->putFileAs($dir, $upload, $name);
                $paths[$field] = $dir.'/'.$name;
            }
        }

        $attributes = $this->persistableAttributes($paths, []);

        if ($record === null) {
            $record = PublicInfoPanel::create($attributes + ['orden' => 0]);
        } else {
            $record->update($attributes);
        }

        $this->redirectRoute('centinela.admin.paneles-publicos.show', ['panel' => $record->getKey()]);
    }

    public function deletePanel(): void
    {
        $this->requireAdmin();

        $record = $this->record();
        if ($record === null) {
            return;
        }

        $record->delete();

        $this->redirectRoute('centinela.admin.paneles-publicos');
    }

    public function render(): View
    {
        $this->requireAdmin();

        $record = $this->record();

        /** @var View|\Illuminate\View\View $view */
        $view = view('livewire.configuracion.public-info-panels.editor', [
            'record' => $record,
            'isEdit' => $record !== null,
            'slugLocked' => $this->isRecordPublished(),
            'thumbnailUrl' => $this->previewImageUrl($record),
            'sharePanel' => $this->sharePanel(),
        ]);

        return $view->extends('voyager::master')->section('content');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $mimesZip = 'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream';

        return [
            'titulo' => ['required', 'string', 'max:100'],
            'tipo' => ['required', Rule::in([PublicInfoPanel::TIPO_HTML_BUNDLE, PublicInfoPanel::TIPO_EMBED_EXTERNO])],
            'publicado' => ['boolean'],
            'version_panel' => ['nullable', 'string', 'max:64'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('public_info_panels', 'slug')->ignore($this->panelId)],
            'descripcion' => ['required', 'string', 'max:400'],
            'autor' => ['nullable', 'string', 'max:255'],
            'autor_email' => ['nullable', 'email:rfc', 'max:255'],
            'institucion' => ['nullable', 'string', 'max:255'],
            'zip_precarga' => ['nullable', 'file', $mimesZip, 'max:56320'],
            'embed_url' => [
                Rule::requiredIf(fn (): bool => $this->tipo === PublicInfoPanel::TIPO_EMBED_EXTERNO && $this->publicado),
                'nullable', 'url', 'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->tipo === PublicInfoPanel::TIPO_EMBED_EXTERNO && filled($value) && ! PublicInfoPanelEmbedUrl::isAllowed(is_string($value) ? $value : null)) {
                        $fail('La URL debe ser https y pertenecer a un host allowlist (Power BI, Tableau, Looker Studio, Datawrapper, Flourish o ArcGIS público).');
                    }
                },
            ],
            'htmlUpload' => ['nullable', 'file', 'mimetypes:text/html,application/xhtml+xml', 'max:25600'],
            'csvUpload' => ['nullable', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:51200'],
            'previewUpload' => ['nullable', 'file', 'mimetypes:image/png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'titulo' => 'título',
            'descripcion' => 'descripción',
            'autor_email' => 'correo del autor',
            'institucion' => 'institución',
            'version_panel' => 'versión del panel',
            'embed_url' => 'URL de embed',
            'zip_precarga' => 'ZIP',
            'htmlUpload' => 'HTML',
            'csvUpload' => 'CSV',
            'previewUpload' => 'preview PNG',
        ];
    }

    private function record(): ?PublicInfoPanel
    {
        return $this->panelId === null ? null : PublicInfoPanel::query()->find($this->panelId);
    }

    private function isRecordPublished(): bool
    {
        return (bool) $this->record()?->publicado;
    }

    /**
     * @param  array<string, string|null>  $paths
     * @param  array<string, string>  $overrides
     * @return array<string, mixed>
     */
    private function persistableAttributes(array $paths, array $overrides): array
    {
        $paths = array_merge($paths, $overrides);

        $attributes = [
            'titulo' => $this->titulo,
            'tipo' => $this->tipo,
            'publicado' => $this->publicado,
            'version_panel' => $this->filledOrNull($this->version_panel),
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'autor' => $this->filledOrNull($this->autor),
            'autor_email' => $this->filledOrNull($this->autor_email),
            'institucion' => $this->filledOrNull($this->institucion),
            'preview_png_path' => $paths['preview_png_path'] ?? null,
        ];

        if ($this->tipo === PublicInfoPanel::TIPO_EMBED_EXTERNO) {
            $attributes['embed_url'] = $this->filledOrNull($this->embed_url);
        } else {
            $attributes['html_path'] = $paths['html_path'] ?? null;
            $attributes['csv_path'] = $paths['csv_path'] ?? null;
        }

        return $attributes;
    }

    /**
     * Completa las llaves de assets que la guarda lee aunque el tipo no las persista.
     *
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function assertableAssets(array $candidate): array
    {
        return [
            'embed_url' => $candidate['embed_url'] ?? null,
            'html_path' => $candidate['html_path'] ?? null,
            'csv_path' => $candidate['csv_path'] ?? null,
        ];
    }

    private function reportGuardFailure(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError((string) $field, (string) ($messages[0] ?? 'Valor inválido.'));
        }

        $message = (string) (collect($exception->errors())->flatten()->first() ?: 'Requisitos de publicación incompletos.');
        $this->dispatch('toast', type: 'danger', message: 'No se pudo guardar: '.$message);
    }

    private function filledOrNull(?string $value): ?string
    {
        return filled($value) ? $value : null;
    }

    private function safeFilename(string $original, string $field): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($original)) ?? '';
        $name = ltrim($name, '.');

        if ($name !== '') {
            return $name;
        }

        return match ($field) {
            'html_path' => 'panel.html',
            'csv_path' => 'datos.csv',
            default => 'preview.png',
        };
    }
}
