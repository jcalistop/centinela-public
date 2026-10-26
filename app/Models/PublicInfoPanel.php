<?php

namespace App\Models;

use App\Support\Dashboard\PublicInfoPanelPreview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class PublicInfoPanel extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_HTML_BUNDLE = 'html_bundle';

    public const TIPO_EMBED_EXTERNO = 'embed_externo';

    protected $fillable = [
        'slug',
        'tipo',
        'titulo',
        'descripcion',
        'autor',
        'autor_email',
        'institucion',
        'version_panel',
        'html_path',
        'csv_path',
        'preview_png_path',
        'embed_url',
        'orden',
        'publicado',
    ];

    protected $casts = [
        'orden' => 'integer',
        'publicado' => 'boolean',
    ];

    public function isHtmlBundle(): bool
    {
        return ($this->tipo ?: self::TIPO_HTML_BUNDLE) === self::TIPO_HTML_BUNDLE;
    }

    public function isEmbedExterno(): bool
    {
        return $this->tipo === self::TIPO_EMBED_EXTERNO;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('publicado', true)
            ->whereNotNull('preview_png_path')
            ->where('preview_png_path', '!=', '')
            ->where(function (Builder $outer): void {
                $outer->where(function (Builder $bundle): void {
                    $bundle->where(function (Builder $tipo): void {
                        $tipo->where('tipo', self::TIPO_HTML_BUNDLE)
                            ->orWhereNull('tipo')
                            ->orWhere('tipo', '');
                    })
                        ->whereNotNull('html_path')->where('html_path', '!=', '')
                        ->whereNotNull('csv_path')->where('csv_path', '!=', '');
                })->orWhere(function (Builder $embed): void {
                    $embed->where('tipo', self::TIPO_EMBED_EXTERNO)
                        ->whereNotNull('embed_url')->where('embed_url', '!=', '');
                });
            });
    }

    public function hasRequiredAssets(): bool
    {
        if (! filled($this->preview_png_path)) {
            return false;
        }

        if ($this->isEmbedExterno()) {
            return filled($this->embed_url);
        }

        return filled($this->html_path) && filled($this->csv_path);
    }

    public function frameSrc(): ?string
    {
        if ($this->isEmbedExterno()) {
            return filled($this->embed_url) ? $this->embed_url : null;
        }

        return $this->htmlEmbedUrl();
    }

    public function previewUrl(): ?string
    {
        if (! filled($this->preview_png_path)) {
            return null;
        }

        return route('centinela.storage.public-panel', [
            'slug' => $this->slug,
            'asset' => 'preview',
        ]);
    }

    public function htmlEmbedUrl(): ?string
    {
        if (! filled($this->html_path)) {
            return null;
        }

        if (! $this->publicado) {
            return PublicInfoPanelPreview::signedTypedUrl($this->slug, 'html');
        }

        return route('centinela.storage.public-panel', [
            'slug' => $this->slug,
            'asset' => 'html',
        ]);
    }

    public function csvDownloadUrl(): ?string
    {
        if (! filled($this->csv_path)) {
            return null;
        }

        return route('centinela.storage.public-panel', [
            'slug' => $this->slug,
            'asset' => 'csv',
        ]);
    }

    public function absoluteAssetPath(string $column): ?string
    {
        $relative = $this->{$column} ?? null;
        if (! is_string($relative) || $relative === '') {
            return null;
        }

        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        if (! $disk->exists($relative)) {
            return null;
        }

        return $disk->path($relative);
    }
}
