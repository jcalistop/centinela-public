@php
    /** @var \App\Models\PublicInfoPanel $sharePanel */
    $shareUrl = \App\Support\Dashboard\PublicInfoPanelShare::url($sharePanel);
    $shareMailto = \App\Support\Dashboard\PublicInfoPanelShare::mailtoHref($sharePanel);
@endphp

<div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
    role="dialog">
    <div class="modal-dialog" role="document" style="margin: 80px auto;">
        <div class="modal-content" x-data="{
            copied: false,
            url: {{ \Illuminate\Support\Js::from($shareUrl) }},
            async copy() {
                try {
                    await navigator.clipboard.writeText(this.url);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                } catch (e) {
                    this.copied = false;
                }
            }
        }">
            <div class="modal-header">
                <button type="button" class="close" wire:click="closeShare">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    {{ $sharePanel->publicado ? 'Compartir panel publicado' : 'Compartir revisión del panel' }}
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">
                    @if ($sharePanel->publicado)
                        Enlace público del dashboard. Cualquier persona con el enlace puede verlo.
                    @else
                        Enlace temporal sin login. No aparece en la landing pública.
                    @endif
                </p>
                <label for="share-url">
                    @if ($sharePanel->publicado)
                        URL pública
                    @else
                        URL de revisión ({{ \App\Support\Dashboard\PublicInfoPanelPreview::ttlDays() }} días)
                    @endif
                </label>
                <div class="input-group">
                    <input id="share-url" type="text" class="form-control" readonly value="{{ $shareUrl }}">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-primary" x-on:click="copy()">
                            <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
                        </button>
                    </span>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ $shareMailto }}" target="_blank" rel="noopener noreferrer" class="btn btn-default">
                    Abrir correo
                </a>
                <button type="button" class="btn btn-default" wire:click="closeShare">Cerrar</button>
            </div>
        </div>
    </div>
</div>
