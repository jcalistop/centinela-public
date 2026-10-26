@php
    /** @var \App\Models\PublicInfoPanel $panel */
    $previewDescription = match (true) {
        !filled($panel->frameSrc()) => 'Cuando el panel tenga HTML o embed, aquí se muestra igual que en el dashboard público.',
        (bool) $panel->publicado => 'Así se ve el panel publicado.',
        default => 'Revisión — el panel aún no está en el dashboard público.',
    };
@endphp

<div>
    <div class="page-content container-fluid">
        <h1 class="page-title">
            <i class="voyager-documentation"></i> {{ $panel->titulo }}
        </h1>

        <p>
            <a href="{{ route('centinela.admin.paneles-publicos') }}" class="btn btn-default">
                <i class="voyager-angle-left"></i> Volver al listado
            </a>
            <a href="{{ route('centinela.admin.paneles-publicos.edit', ['panel' => $panel->id]) }}"
                class="btn btn-primary">
                <i class="voyager-edit"></i> Editar
            </a>
            @if ($panel->hasRequiredAssets())
                <button type="button" class="btn btn-default" wire:click="openShare({{ $panel->id }})">
                    {{ $panel->publicado ? 'Compartir panel' : 'Compartir revisión' }}
                </button>
            @endif
            <button type="button" class="btn btn-default" wire:click="openAgentGuide">
                Contratos para agentes
            </button>
        </p>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3 class="panel-title">Resumen</h3></div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-3"><strong>Tipo</strong><br>{{ $panel->isEmbedExterno() ? 'Embed' : 'HTML' }}</div>
                    <div class="col-md-3"><strong>Publicado</strong><br>
                        <span class="label label-{{ $panel->publicado ? 'success' : 'default' }}">
                            {{ $panel->publicado ? 'Sí' : 'No' }}
                        </span>
                    </div>
                    <div class="col-md-3"><strong>Versión</strong><br>{{ $panel->version_panel ?: '—' }}</div>
                    <div class="col-md-3"><strong>Slug</strong><br><code>{{ $panel->slug }}</code></div>
                </div>
                <hr>
                <p style="margin-bottom: 0;"><strong>Descripción</strong><br>{{ $panel->descripcion }}</p>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3 class="panel-title">Autoría</h3></div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4"><strong>Autor</strong><br>{{ $panel->autor ?: '—' }}</div>
                    <div class="col-md-4"><strong>Correo</strong><br>{{ $panel->autor_email ?: '—' }}</div>
                    <div class="col-md-4"><strong>Institución</strong><br>{{ $panel->institucion ?: '—' }}</div>
                </div>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3 class="panel-title">Origen</h3></div>
            <div class="panel-body">
                <p class="text-muted">Archivos curados o URL de embed. La miniatura es la del hub público.</p>
                <div class="row">
                    @if ($panel->isEmbedExterno())
                        <div class="col-md-12">
                            <strong>URL de embed</strong><br>
                            @if (filled($panel->embed_url))
                                <a href="{{ $panel->embed_url }}" target="_blank" rel="noopener noreferrer">{{ $panel->embed_url }}</a>
                            @else
                                —
                            @endif
                        </div>
                    @else
                        <div class="col-md-6"><strong>HTML</strong><br><code>{{ $panel->html_path ?: '—' }}</code></div>
                        <div class="col-md-6"><strong>CSV</strong><br><code>{{ $panel->csv_path ?: '—' }}</code></div>
                    @endif
                </div>
                @if ($thumbnailUrl)
                    <div style="margin-top: 15px;">
                        <strong>Miniatura</strong><br>
                        <img src="{{ $thumbnailUrl }}" alt="Miniatura de {{ $panel->titulo }}"
                            style="max-height: 80px; border: 1px solid #e4eaec; border-radius: 4px;">
                    </div>
                @endif
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading"><h3 class="panel-title">Vista previa</h3></div>
            <div class="panel-body">
                <p class="text-muted">{{ $previewDescription }}</p>
                @include('livewire.configuracion.public-info-panels.partials.preview-frame', ['panel' => $panel])
            </div>
        </div>
    </div>

    @if ($showShare && $sharePanel !== null)
        @include('livewire.configuracion.public-info-panels.partials.share-modal', ['sharePanel' => $sharePanel])
    @endif

    @if ($showAgentGuide)
        @include('livewire.configuracion.public-info-panels.partials.agent-guide-modal')
    @endif
</div>
