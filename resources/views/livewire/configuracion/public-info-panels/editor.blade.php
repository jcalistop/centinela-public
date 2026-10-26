@php
    /** @var \App\Models\PublicInfoPanel|null $record */
    $isBundle = $tipo === \App\Models\PublicInfoPanel::TIPO_HTML_BUNDLE;
    $isEmbed = $tipo === \App\Models\PublicInfoPanel::TIPO_EMBED_EXTERNO;
@endphp

<div>
    <div class="page-content container-fluid">
        <h1 class="page-title">
            <i class="voyager-documentation"></i> {{ $isEdit ? 'Editar panel público' : 'Nuevo panel público' }}
        </h1>

        <p>
            <a href="{{ route('centinela.admin.paneles-publicos') }}" class="btn btn-default">
                <i class="voyager-angle-left"></i> Volver al listado
            </a>
            @if ($isEdit)
                <a href="{{ route('centinela.admin.paneles-publicos.show', ['panel' => $record->id]) }}"
                    class="btn btn-default">
                    <i class="voyager-eye"></i> Ver
                </a>
                @if ($record->hasRequiredAssets())
                    <button type="button" class="btn btn-default" wire:click="openShare({{ $record->id }})">
                        {{ $record->publicado ? 'Compartir panel' : 'Compartir revisión' }}
                    </button>
                @endif
            @endif
            <button type="button" class="btn btn-default" wire:click="openAgentGuide">
                Contratos para agentes
            </button>
        </p>

        <form wire:submit="save">
            <div class="panel panel-bordered">
                <div class="panel-heading"><h3 class="panel-title">Resumen</h3></div>
                <div class="panel-body">
                    <div class="row">
                        <div class="form-group col-md-12 @error('titulo') has-error @enderror">
                            <label for="titulo">Título</label>
                            <input id="titulo" type="text" class="form-control" maxlength="100"
                                wire:model.blur="titulo">
                            @error('titulo')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-4 @error('tipo') has-error @enderror">
                            <label for="tipo">Tipo de panel</label>
                            <select id="tipo" class="form-control" wire:model.live="tipo">
                                <option value="{{ \App\Models\PublicInfoPanel::TIPO_HTML_BUNDLE }}">Paquete HTML + CSV + PNG</option>
                                <option value="{{ \App\Models\PublicInfoPanel::TIPO_EMBED_EXTERNO }}">Embed externo (URL pública)</option>
                            </select>
                            <span class="help-block">El embed externo solo acepta hosts allowlist (Power BI, Tableau, Looker Studio, etc.).</span>
                            @error('tipo')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-4">
                            <label for="publicado">Publicado</label>
                            <div class="checkbox" style="margin-top: 0;">
                                <label>
                                    <input id="publicado" type="checkbox" wire:model.live="publicado"> Publicar en el dashboard público
                                </label>
                            </div>
                            <span class="help-block">
                                {{ $isEmbed ? 'Requiere URL de embed allowlist + PNG de preview.' : 'Requiere HTML, CSV y PNG de preview.' }}
                            </span>
                        </div>
                        <div class="form-group col-md-4 @error('version_panel') has-error @enderror">
                            <label for="version_panel">Versión del panel</label>
                            <input id="version_panel" type="text" class="form-control" maxlength="64"
                                wire:model="version_panel">
                            <span class="help-block">Texto libre (p. ej. 1.2 o 2026-10).</span>
                            @error('version_panel')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-12 @error('slug') has-error @enderror">
                            <label for="slug">Slug</label>
                            <input id="slug" type="text" class="form-control" maxlength="255" wire:model="slug"
                                @disabled($slugLocked)>
                            <span class="help-block">Usado en /publico/paneles/{slug}. Editable solo si el panel no está publicado.</span>
                            @error('slug')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-12 @error('descripcion') has-error @enderror">
                            <label for="descripcion">Descripción</label>
                            <textarea id="descripcion" class="form-control" rows="3" maxlength="400" wire:model="descripcion"></textarea>
                            @error('descripcion')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-bordered">
                <div class="panel-heading"><h3 class="panel-title">Autoría</h3></div>
                <div class="panel-body">
                    <div class="row">
                        <div class="form-group col-md-4 @error('autor') has-error @enderror">
                            <label for="autor">Autor</label>
                            <input id="autor" type="text" class="form-control" maxlength="255" wire:model="autor">
                            @error('autor')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-4 @error('autor_email') has-error @enderror">
                            <label for="autor_email">Correo del autor</label>
                            <input id="autor_email" type="email" class="form-control" maxlength="255"
                                wire:model.blur="autor_email">
                            <span class="help-block">Se valida el formato del correo al guardar (opcional).</span>
                            @error('autor_email')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-4 @error('institucion') has-error @enderror">
                            <label for="institucion">Institución</label>
                            <input id="institucion" type="text" class="form-control" maxlength="255"
                                wire:model="institucion">
                            @error('institucion')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-bordered">
                <div class="panel-heading"><h3 class="panel-title">Origen</h3></div>
                <div class="panel-body">
                    <p class="text-muted">Archivos curados o URL de embed. La miniatura es la del hub público.</p>

                    <div class="form-group @error('zip_precarga') has-error @enderror">
                        <label for="zip_precarga">Precargar desde ZIP</label>
                        <input id="zip_precarga" type="file" class="form-control" accept=".zip"
                            wire:model="zip_precarga" wire:loading.attr="disabled" wire:target="zip_precarga">
                        <span class="help-block">
                            Opcional. Contrato: manifest.json + preview.png (+ panel.html/datos.csv o embed_url). No publica al subir; revisa y guarda.
                        </span>
                        <span class="help-block" wire:loading wire:target="zip_precarga">Procesando ZIP…</span>
                        @error('zip_precarga')<span class="help-block">{{ $message }}</span>@enderror
                    </div>

                    @if ($isEmbed)
                        <div class="form-group @error('embed_url') has-error @enderror">
                            <label for="embed_url">URL de embed</label>
                            <input id="embed_url" type="url" class="form-control" maxlength="2048"
                                wire:model.blur="embed_url">
                            <span class="help-block">Solo https en hosts permitidos. El contenido debe ser públicamente accesible en el origen.</span>
                            @error('embed_url')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    @endif

                    @if ($isBundle)
                        <div class="form-group @error('htmlUpload') has-error @enderror @error('html_path') has-error @enderror">
                            <label for="htmlUpload">HTML</label>
                            <input id="htmlUpload" type="file" class="form-control" accept=".html,.htm,text/html"
                                wire:model="htmlUpload">
                            @if (filled($html_path))
                                <span class="help-block">Actual: <code>{{ $html_path }}</code></span>
                            @endif
                            <span class="help-block">Diseño del panel. Rutas relativas al CSV (p. ej. datos.csv) se resuelven en el guest sin editar el HTML. Sin CDN ni javascript:.</span>
                            @error('htmlUpload')<span class="help-block">{{ $message }}</span>@enderror
                            @error('html_path')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group @error('csvUpload') has-error @enderror @error('csv_path') has-error @enderror">
                            <label for="csvUpload">CSV</label>
                            <input id="csvUpload" type="file" class="form-control" accept=".csv,text/csv"
                                wire:model="csvUpload">
                            @if (filled($csv_path))
                                <span class="help-block">Actual: <code>{{ $csv_path }}</code></span>
                            @endif
                            <span class="help-block">Datos (máx. 50 MB). El guest mapea cualquier *.csv relativo del HTML a este archivo.</span>
                            @error('csvUpload')<span class="help-block">{{ $message }}</span>@enderror
                            @error('csv_path')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    @endif

                    <div class="form-group @error('previewUpload') has-error @enderror @error('preview_png_path') has-error @enderror">
                        <label for="previewUpload">Preview PNG</label>
                        <input id="previewUpload" type="file" class="form-control" accept="image/png"
                            wire:model="previewUpload">
                        @if ($thumbnailUrl)
                            <img src="{{ $thumbnailUrl }}" alt="Miniatura actual"
                                style="display:block; max-height: 80px; margin-top: 8px; border: 1px solid #e4eaec; border-radius: 4px;">
                        @elseif (filled($preview_png_path))
                            <span class="help-block">Actual: <code>{{ $preview_png_path }}</code></span>
                        @endif
                        <span class="help-block">Miniatura del hub guest.</span>
                        @error('previewUpload')<span class="help-block">{{ $message }}</span>@enderror
                        @error('preview_png_path')<span class="help-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <div class="panel panel-bordered">
                <div class="panel-heading"><h3 class="panel-title">Vista previa</h3></div>
                <div class="panel-body">
                    <p class="text-muted">Muestra el último guardado. Guarda para actualizar el iframe.</p>
                    @include('livewire.configuracion.public-info-panels.partials.preview-frame', ['panel' => $record])
                </div>
            </div>

            <div class="clearfix" style="margin-bottom: 30px;">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <i class="voyager-check"></i> Guardar
                </button>
                @if ($isEdit)
                    <button type="button" class="btn btn-danger pull-right"
                        wire:click="deletePanel" wire:confirm="¿Eliminar este panel? Esta acción no se puede deshacer.">
                        <i class="voyager-trash"></i> Eliminar
                    </button>
                @endif
            </div>
        </form>
    </div>

    @if ($showShare && $sharePanel !== null)
        @include('livewire.configuracion.public-info-panels.partials.share-modal', ['sharePanel' => $sharePanel])
    @endif

    @if ($showAgentGuide)
        @include('livewire.configuracion.public-info-panels.partials.agent-guide-modal')
    @endif
</div>
