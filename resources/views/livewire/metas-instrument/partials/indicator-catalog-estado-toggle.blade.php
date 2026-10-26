<div style="margin-top: 15px; margin-right: 20px;">
    <span class="metas-panel-estado-label">Estado</span>
    <label class="switch-toggle metas-panel-estado-toggle" style="margin-bottom:0;">
        <input type="checkbox" wire:model.live="estado">
        <span class="switch-slider"></span>
    </label>
    <span class="switch-label">{{ $estado ? 'Activo' : 'Inactivo' }}</span>
</div>
