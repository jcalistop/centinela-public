@extends('voyager::master')

@php
    $isEdit = $indicatorType->exists;
@endphp

@section('page_title', $isEdit ? 'Editar instrumento' : 'Nuevo instrumento Metas')

@section('page_header')
    <div class="container-fluid" style="text-align:left">
        <h1 class="page-title" style="margin:0 0 10px; text-align:left">
            <i class="{{ $isEdit ? 'voyager-edit' : 'voyager-plus' }}"></i>
            @if ($isEdit)
                {{ $indicatorType->nombre_indicador }}
            @else
                Nuevo instrumento (Metas sanitarias)
            @endif
        </h1>
        <a href="{{ route('voyager.' . $dataType->slug . '.index') }}" class="btn btn-primary" style="display:inline-block; float:none; margin:0">
            <i class="voyager-angle-left"></i> Volver al listado
        </a>
    </div>
@stop

@section('content')
    <div class="page-content container-fluid it-type-edit">
        @include('voyager::alerts')

        @if (! $isEdit)
            <div class="alert alert-info" style="border-radius:2px; margin-bottom:20px">
                <strong><i class="voyager-info-circled"></i> Qué estás creando</strong>
                <p style="margin:8px 0 0">
                    Un <em>instrumento</em> define la URL bajo <code>/admin/</code>, la visibilidad en menú y si la medición es por
                    establecimiento o por unidad interna. Los slugs de metas deben seguir el patrón
                    <code>metas-…</code> y no deben chocar con otras rutas.
                </p>
            </div>
        @endif

        <form method="post"
            action="{{ $isEdit ? route('voyager.' . $dataType->slug . '.update', $indicatorType->getKey()) : route('voyager.' . $dataType->slug . '.store') }}"
            class="it-type-edit__form">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title" style="display:flex; align-items:center; gap:8px">
                        <i class="voyager-params"></i> Datos del instrumento
                    </h3>
                </div>
                <div class="panel-body" style="padding:24px 20px 20px">
                    @include('voyager::instrumentos-evaluacion._form-fields', [
                        'indicatorType' => $indicatorType,
                        'isEdit' => $isEdit,
                        'metasRouteSlugs' => $metasRouteSlugs ?? null,
                        'routesInUse' => $routesInUse ?? null,
                    ])
                </div>
                <div class="panel-footer" style="background:#f9f9f9; border-top:1px solid #e7e7e7; padding:16px 20px">
                    <div class="clearfix" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px">
                        <span class="text-muted" style="font-size:12px; max-width:420px">
                            @if ($isEdit)
                                Los cambios en granularidad u otros campos pueden afectar enlaces, menú o módulo Metas. El slug
                                de URL no se modifica desde aquí.
                            @else
                                Tras guardar podrás activar el instrumento y ajustar visibilidad u orden desde el gestor
                                de menú.
                            @endif
                        </span>
                        <div>
                            <button type="submit" class="btn btn-primary" style="min-width:120px">
                                <i class="voyager-check"></i> {{ $isEdit ? 'Guardar cambios' : 'Crear instrumento' }}
                            </button>
                            <a href="{{ route('voyager.' . $dataType->slug . '.index') }}" class="btn btn-default"
                                style="margin-left:4px">Cancelar</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@stop

@section('css')
    <style>
        .it-type-edit__section {
            margin-bottom: 28px
        }
        .it-type-edit__section h4 {
            color: #555;
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee
        }
        .it-type-edit__section h4 i {
            margin-right: 6px;
            color: #777
        }
        .it-icon-picker__grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            margin-top: 4px;
            border: 1px solid #e7e7e7;
            border-radius: 2px;
            background: #fafafa
        }
        .it-icon-pick {
            min-width: 40px;
            padding: 6px 8px !important;
            line-height: 1.2
        }
        .it-icon-pick.it-icon-pick--active {
            border-color: #22a7f0;
            box-shadow: inset 0 0 0 1px #22a7f0;
            background: #eef9ff
        }
        .it-icon-pick i {
            font-size: 18px;
            vertical-align: middle
        }
    </style>
@stop

@section('javascript')
    <script>
        $(function() {
            var $icon = $('#icon_class');
            var $pre = $('#it-icon-preview i');
            if (!$icon.length) {
                return;
            }
            var $picks = $('.it-icon-pick');
            function refreshIconPreview() {
                var c = ($icon.val() || 'voyager-book').trim();
                $pre.attr('class', c);
            }
            function syncPickerSelection() {
                var cur = ($icon.val() || '').trim();
                $picks.removeClass('it-icon-pick--active');
                $picks.filter(function() {
                    return $(this).attr('data-class') === cur;
                }).addClass('it-icon-pick--active');
            }
            $picks.on('click', function() {
                var cls = $(this).attr('data-class');
                if (typeof cls === 'string' && cls.length) {
                    $icon.val(cls);
                    refreshIconPreview();
                    syncPickerSelection();
                }
            });
            $icon.on('input change blur', function() {
                refreshIconPreview();
                syncPickerSelection();
            });
            refreshIconPreview();
            syncPickerSelection();
        });
    </script>
@stop
