@props(['cutoffs'])

<div id="MassiveEditableTabs" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('comges.pestanas-editables.masivo') }}">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title modal-success">Pestañas editables por referente</h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted" style="margin-bottom: 12px;">
                        Aplica a todos los indicadores COMGES del año vigente, para tu Servicio de Salud.
                    </p>

                    <div class="form-group">
                        <label for="massive_editable_tabs_cutoff_id">Corte</label>
                        <select id="massive_editable_tabs_cutoff_id" name="cutoff_id" class="form-control" required>
                            <option value="">Seleccione un corte</option>
                            @foreach ($cutoffs as $cutoff)
                                <option value="{{ $cutoff->id }}">{{ $cutoff->nombre_corte }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="massive_editable_tabs_mode">Acción</label>
                        <select id="massive_editable_tabs_mode" name="mode" class="form-control" required>
                            <option value="enable">Activar (hacer editable)</option>
                            <option value="disable">Desactivar (dejar no editable)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-sm btn-warning">
                        <i class="glyphicon glyphicon-check"></i> Aplicar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <style>
        .modal-header {
            background-color: #337ab7;
            color: white;
            border-bottom: 2px solid #337ab7;
        }
    </style>
</div>
