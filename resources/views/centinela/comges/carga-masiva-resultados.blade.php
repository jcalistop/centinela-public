@extends('voyager::master')

@section('page_title', 'Carga masiva de resultados COMGES')

@section('content')
    <div class="page-content container-fluid">
        @include('voyager::alerts')

        <h1 class="page-title">
            <i class="voyager-upload"></i> Carga masiva de resultados COMGES
        </h1>

        <div style="margin-bottom:15px">
            <a href="{{ route('voyager.compromisos.index') }}" class="btn btn-default btn-sm">
                <i class="voyager-angle-left"></i> Volver a compromisos
            </a>
        </div>

        <div class="panel panel-primary">
            <div class="panel-heading">
                <h4 class="panel-title">Selecciona tipo de evaluación</h4>
            </div>
            <div class="panel-body">
                @if (!$isAvailable)
                    <div class="alert alert-warning" style="margin-bottom: 10px;">
                        Disponible solo desde el año 2025. Año activo: <strong>{{ $currentYearLabel }}</strong>
                    </div>
                @endif

                <form method="GET" action="{{ route('comges.carga-masiva-resultados.index') }}" class="form-inline"
                    id="tipo-evaluacion-form">
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="tipo_evaluacion" style="margin-right: 6px;">Tipo</label>
                        <select id="tipo_evaluacion" name="tipo_evaluacion" class="form-control">
                            <option value="AUTOEVALUACION" @selected($tipoEvaluacion === 'AUTOEVALUACION')>Autoevaluación</option>
                            <option value="PRELIMINAR" @selected($tipoEvaluacion === 'PRELIMINAR')>Preliminar</option>
                            <option value="FINAL" @selected($tipoEvaluacion === 'FINAL')>Final</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-default js-apply-tipo-evaluacion">
                        <i class="voyager-refresh"></i> Cambiar
                    </button>
                </form>

                <script>
                    (function() {
                        var form = document.getElementById('tipo-evaluacion-form');
                        var select = document.getElementById('tipo_evaluacion');
                        var applyButton = document.querySelector('.js-apply-tipo-evaluacion');

                        if (!form || !select) {
                            return;
                        }

                        if (applyButton) {
                            applyButton.style.display = 'none';
                        }

                        select.addEventListener('change', function() {
                            form.submit();
                        });
                    })();
                </script>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">1) Descargar planilla (formato)</h4>
            </div>
            <div class="panel-body">
                <a href="{{ $isAvailable ? route('comges.carga-masiva-resultados.plantilla', ['tipo_evaluacion' => $tipoEvaluacion]) : '#' }}"
                    class="btn btn-warning @if (!$isAvailable) disabled @endif"
                    @if (!$isAvailable) aria-disabled="true" @endif>
                    <i class="voyager-download"></i> Descargar XLSX con datos actuales
                </a>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">2) Importar planilla actualizada</h4>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('comges.carga-masiva-resultados.importar') }}"
                    enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="tipo_evaluacion" value="{{ $tipoEvaluacion }}">

                    <div class="form-group @error('archivo') has-error @enderror" style="margin-bottom:8px">
                        <label for="import-archivo">Archivo XLSX</label>
                        <input type="file" id="import-archivo" name="archivo" accept=".xlsx" class="form-control"
                            data-max-upload-bytes="{{ \App\Support\Upload\UploadSizeLimit::importSpreadsheetMaxKilobytes() * 1024 }}"
                            data-max-upload-label="{{ \App\Support\Upload\UploadSizeLimit::humanLabelForKilobytes(\App\Support\Upload\UploadSizeLimit::importSpreadsheetMaxKilobytes()) }}"
                            @disabled(!$isAvailable)>
                        <x-upload-size-hint :kilobytes="\App\Support\Upload\UploadSizeLimit::importSpreadsheetMaxKilobytes()" />
                        @error('archivo')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-success" @disabled(!$isAvailable)>
                        <i class="voyager-upload"></i> Enviar a cola
                    </button>
                </form>

                <p class="text-muted" style="margin-top:10px; margin-bottom:0;">
                    La importación se procesa en segundo plano y se reporta por correo electrónico, incluyendo filas con
                    error.
                </p>
            </div>
        </div>
    </div>
@stop
