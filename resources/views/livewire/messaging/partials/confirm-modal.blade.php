<div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
    role="dialog">
    <div class="modal-dialog" role="document" style="margin: 80px auto;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" wire:click="closeConfirm">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Confirmar envío</h4>
            </div>
            <div class="modal-body">
                <p>
                    @if ($previewCount === null)
                        Calcule primero los destinatarios.
                    @else
                        Se encolarán {{ $previewCount }} correo(s). El envío se fraccionará en olas.
                    @endif
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" wire:click="closeConfirm">Cancelar</button>
                <button type="button" class="btn btn-primary" wire:click="sendCampaign"
                    @disabled($previewCount === null || $previewCount < 1)>
                    Confirmar y encolar
                </button>
            </div>
        </div>
    </div>
</div>
