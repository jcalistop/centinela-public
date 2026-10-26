<div id="MassiveDateCutoff" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title modal-success">Asignación masiva de fechas de corte</h4>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('asignacion-masiva-fechas') }}" class="form-horizontal">
                    @csrf
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Fecha de apertura:</label>
                        <div class="col-sm-8">
                            <input type="date" name="fecha_apertura" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-4 control-label">Fecha de Cierre:</label>
                        <div class="col-sm-8">
                            <input type="date" name="fecha_cierre" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="submit" class="btn btn-sm btn-warning pull-right">
                                <i class="glyphicon glyphicon-calendar"></i>
                                Asignar fechas
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <style>
        .modal-header {
            background-color: #F2A427;
            color: white;
            border-bottom: 2px solid #F2A427;
        }
    </style>
</div>
