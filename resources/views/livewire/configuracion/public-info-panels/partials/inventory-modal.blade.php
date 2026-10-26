@php
    /** @var array{markdown: string, json: string, total: int} $inventory */
@endphp

<div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
    role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
        <div class="modal-content" x-data="{
            format: 'markdown',
            copied: false,
            markdown: {{ \Illuminate\Support\Js::from($inventory['markdown']) }},
            json: {{ \Illuminate\Support\Js::from($inventory['json']) }},
            get text() { return this.format === 'markdown' ? this.markdown : this.json; },
            async copy() {
                if (!this.text) { return; }
                try {
                    await navigator.clipboard.writeText(this.text);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                } catch (e) {
                    this.copied = false;
                }
            }
        }">
            <div class="modal-header">
                <button type="button" class="close" wire:click="closeInventory">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Copiar inventario</h4>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 10px;">
                    <div class="btn-group">
                        <button type="button" class="btn btn-default btn-sm" x-on:click="format = 'markdown'"
                            x-bind:class="{ active: format === 'markdown' }">Markdown</button>
                        <button type="button" class="btn btn-default btn-sm" x-on:click="format = 'json'"
                            x-bind:class="{ active: format === 'json' }">JSON</button>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm pull-right" x-on:click="copy()"
                        x-bind:disabled="!text">
                        <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
                    </button>
                </div>
                <p class="text-muted" style="font-size: 12px;">
                    @if ($inventory['total'] > 0)
                        El texto queda listo para pegar en el agente externo. No se descarga ningún archivo.
                    @else
                        Sin paneles registrados en el catálogo.
                    @endif
                </p>
                <pre style="max-height: 60vh; overflow: auto; white-space: pre; font-size: 12px;" x-text="text"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" wire:click="closeInventory">Cerrar</button>
            </div>
        </div>
    </div>
</div>
