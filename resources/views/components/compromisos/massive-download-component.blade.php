<div id="MassiveDownload" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('descarga-masiva-comges') }}" id="massiveDownloadForm">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title modal-success">Descarga masiva de medios de verificación</h4>
                </div>
                <div class="modal-body">
                    <p>Seleccione la opción que desea descargar</p>
                    <select name="cutoff_id" class="form-control">
                            <option>Seleccione la opción a descargar</option>
                        @foreach ($cutoffs as $cutoff)
                            <option value="{{ $cutoff->id }}">{{ $cutoff->nombre_corte }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type=submit class="btn btn-sm btn-success">
                        <i class="glyphicon glyphicon-download-alt"></i> Proceder a la descarga
                    </button>
                </div>
            </form>
        </div>
    </div>
    <style>
        .modal-header {
            background-color: #46D17F;
            color: white;
            border-bottom: 2px solid #46D17F;
        }
    </style>
    @push('javascript')
        <script>
            $(document).ready(function() {
                $('#massiveDownloadForm').on('submit', function(event) {
                    setTimeout(function() {
                        $('#MassiveDownload').modal('hide'); // Cierra el modal después de 2 segundos
                    }, 3000);
                    return true
                });
            });
        </script>
    @endpush
</div>
