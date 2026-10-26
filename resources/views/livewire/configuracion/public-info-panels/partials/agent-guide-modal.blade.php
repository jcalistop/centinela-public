<div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
    role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" wire:click="closeAgentGuide">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Contratos para agentes</h4>
            </div>
            <div class="modal-body">
                <pre style="max-height: 60vh; overflow: auto; white-space: pre-wrap; font-size: 12px;">{{ \App\Support\Dashboard\PublicInfoPanelAgentGuide::contents() }}</pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" wire:click="closeAgentGuide">Cerrar</button>
                <button type="button" class="btn btn-primary" wire:click="downloadAgentGuide">Descargar .md</button>
            </div>
        </div>
    </div>
</div>
