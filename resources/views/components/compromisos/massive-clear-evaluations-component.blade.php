<div id="MassiveClearEvaluations" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('limpieza-masiva-autoevaluaciones') }}" id="massiveClearEvaluationsForm">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title modal-danger">Limpieza masiva de autoevaluaciones</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" role="alert">
                        <strong><i class="glyphicon glyphicon-warning-sign"></i> ¡ADVERTENCIA!</strong>
                        <p>Esta acción es <strong>IRREVERSIBLE</strong> y <strong>ELIMINARÁ COMPLETAMENTE</strong> todas
                            las
                            autoevaluaciones del corte seleccionado pertenecientes a su dependencia en el año vigente.
                        </p>
                        <p>Se perderán definitivamente todos los datos asociados (numerador, denominador, resultado,
                            meta,
                            cumplimiento y observaciones).</p>
                        <p>Confirme solo si está absolutamente seguro.</p>
                    </div>
                    <div class="form-group">
                        <label for="cutoff_id">Seleccione una opción a limpiar:</label>
                        <select name="cutoff_id" id="cutoff_id" class="form-control" required>
                            <option value="">Seleccione la opción a limpiar</option>
                            @foreach ($cutoffs as $cutoff)
                                <option value="{{ $cutoff->id }}">{{ $cutoff->nombre_corte }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">
                        <i class="glyphicon glyphicon-remove"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="glyphicon glyphicon-trash"></i> Eliminar autoevaluaciones
                    </button>
                </div>
            </form>
        </div>
    </div>
    <style>
        .modal-header.modal-danger,
        .modal-header {
            background-color: #d9534f;
            color: white;
            border-bottom: 2px solid #d9534f;
        }
    </style>
    @push('javascript')
        <script>
            $(document).ready(function() {
                $('#massiveClearEvaluationsForm').on('submit', function(event) {
                    var cutoffSelect = $('#cutoff_id');
                    if (!cutoffSelect.val()) {
                        alert('Debe seleccionar un corte antes de continuar');
                        event.preventDefault();
                        return false;
                    }

                    var cutoffText = cutoffSelect.find('option:selected').text();
                    var confirmacion = confirm(
                        '¿Está completamente seguro de que desea ELIMINAR TODAS las autoevaluaciones del ' +
                        cutoffText + '?\n\n' +
                        'Esta acción NO se puede deshacer y eliminará por completo los registros (numerador, denominador, resultado, meta, cumplimiento y observaciones).\n\n' +
                        'Presione ACEPTAR para continuar o CANCELAR para detener la operación.'
                    );

                    if (!confirmacion) {
                        event.preventDefault();
                        return false;
                    }

                    return true;
                });
            });
        </script>
    @endpush
</div>
