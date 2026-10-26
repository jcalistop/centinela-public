@extends('voyager::master')

@php
    /** @var \App\Models\IndicatorType $it */
    $it = $dataTypeContent;
@endphp

@section('page_title', 'Instrumento — ' . $it->nombre_indicador)

@section('page_header')
    <div class="container-fluid" style="text-align:left">
        <h1 class="page-title" style="margin:0 0 10px; text-align:left">
            <i class="voyager-eye"></i> {{ $it->nombre_indicador }}
        </h1>
        <a href="{{ route('voyager.' . $dataType->slug . '.index') }}" class="btn btn-primary"
            style="display:inline-block; float:none; margin:0 8px 0 0">
            <i class="voyager-angle-left"></i> Volver al listado
        </a>
        @can('edit', $it)
            <a href="{{ route('voyager.' . $dataType->slug . '.edit', $it->getKey()) }}" class="btn btn-info"
                style="margin-right:4px">
                <i class="voyager-edit"></i> Editar
            </a>
        @endcan
        @can('delete', $it)
            @if ($isSoftDeleted ?? false)
                <a href="{{ route('voyager.' . $dataType->slug . '.restore', $it->getKey()) }}"
                    title="{{ __('voyager::generic.restore') }}" class="btn btn-default restore" data-id="{{ $it->getKey() }}"
                    id="restore-{{ $it->getKey() }}">
                    <i class="voyager-trash"></i> {{ __('voyager::generic.restore') }}
                </a>
            @else
                <a href="javascript:;" title="{{ __('voyager::generic.delete') }}" class="btn btn-danger delete"
                    data-id="{{ $it->getKey() }}" id="delete-{{ $it->getKey() }}">
                    <i class="voyager-trash"></i> {{ __('voyager::generic.delete') }}
                </a>
            @endif
        @endcan
    </div>
    @include('voyager::multilingual.language-selector')
@stop

@section('content')
    <div class="page-content container-fluid it-type-edit it-type-read">
        @include('voyager::alerts')

        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title" style="display:flex; align-items:center; gap:8px">
                    <i class="voyager-params"></i> Datos del instrumento
                </h3>
            </div>
            <div class="panel-body" style="padding:24px 20px 20px">
                <div class="it-type-edit__section">
                    <h4><i class="voyager-tag"></i>Identidad y texto</h4>
                    <table class="table table-bordered" style="margin-bottom:0; max-width:720px">
                        <tbody>
                            <tr>
                                <th style="width:200px">Clave</th>
                                <td><code>{{ $it->clave_indicador }}</code></td>
                            </tr>
                            <tr>
                                <th>Nombre visible</th>
                                <td>{{ $it->nombre_indicador }}</td>
                            </tr>
                            <tr>
                                <th>Descripción</th>
                                <td>{{ $it->descripcion_indicador !== null && $it->descripcion_indicador !== '' ? $it->descripcion_indicador : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="it-type-edit__section">
                    <h4><i class="voyager-compass"></i> URL y apariencia en el admin</h4>
                    <table class="table table-bordered" style="margin-bottom:0; max-width:720px">
                        <tbody>
                            <tr>
                                <th style="width:200px">Slug de URL</th>
                                <td>
                                    <span class="text-muted">/admin/</span><code>{{ $it->route }}</code>
                                </td>
                            </tr>
                            <tr>
                                <th>Icono en menú</th>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:10px">
                                        <i class="{{ $it->icon_class ?: 'voyager-book' }}" style="font-size:20px"
                                            aria-hidden="true"></i>
                                        <code>{{ $it->icon_class ?: '—' }}</code>
                                    </span>
                                </td>
                            </tr>
                            @if ($it->view !== null && $it->view !== '')
                                <tr>
                                    <th>Vista Blade (legacy)</th>
                                    <td><code>{{ $it->view }}</code></td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="it-type-edit__section">
                    <h4><i class="voyager-lab"></i> Medición y publicación</h4>
                    <table class="table table-bordered" style="margin-bottom:0; max-width:720px">
                        <tbody>
                            <tr>
                                <th style="width:200px">Granularidad de medición</th>
                                <td>
                                    {{ \App\Models\IndicatorType::measurementGranularityLabel($it->measurement_granularity) }}
                                    <span class="text-muted"
                                        style="font-size:12px">(<code>{{ $it->measurement_granularity ?? '—' }}</code>)</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Visibilidad</th>
                                <td>
                                    @if ($it->estado)
                                        <span class="label label-success">Activo</span>
                                    @else
                                        <span class="label label-default">Inactivo</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="help-block" style="margin-top:12px; max-width:720px">El orden en el menú lateral no se
                        edita aquí; se gestiona con el componente de menú.</p>
                </div>

                <div class="it-type-edit__section" style="margin-bottom:0">
                    <h4><i class="voyager-data"></i> Técnico</h4>
                    <p class="text-muted" style="font-size:12px; margin:0">
                        ID {{ $it->getKey() }}
                        @if ($it->created_at)
                            · creado {{ $it->created_at->format('Y-m-d H:i') }}
                        @endif
                        @if ($it->updated_at)
                            · actualizado {{ $it->updated_at->format('Y-m-d H:i') }}
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"
                        aria-label="{{ __('voyager::generic.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-trash"></i> {{ __('voyager::generic.delete_question') }}
                        {{ strtolower($dataType->getTranslatedAttribute('display_name_singular')) }}?</h4>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('voyager.' . $dataType->slug . '.index') }}" id="delete_form" method="POST">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm"
                            value="{{ __('voyager::generic.delete_confirm') }} {{ strtolower($dataType->getTranslatedAttribute('display_name_singular')) }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right"
                        data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                </div>
            </div>
        </div>
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
    </style>
@stop

@section('javascript')
    @if ($isModelTranslatable ?? false)
        <script>
            $(document).ready(function() {
                $('.side-body').multilingual();
            });
        </script>
    @endif
    <script>
        var deleteFormAction;
        $('.delete').on('click', function() {
            var form = $('#delete_form')[0];
            if (!deleteFormAction) {
                deleteFormAction = form.action;
            }
            form.action = deleteFormAction.match(/\/[0-9]+$/) ?
                deleteFormAction.replace(/([0-9]+$)/, $(this).data('id')) :
                deleteFormAction + '/' + $(this).data('id');
            $('#delete_modal').modal('show');
        });
    </script>
    @include('partials.voyager.bootstrap-modal-stack', ['modalIds' => ['#delete_modal']])
@stop
