@extends('voyager::master')

@section('css')

    @include('voyager::compass.includes.styles')

@stop

@section('page_title', __('voyager::generic.view') . ' ' . $dataType->getTranslatedAttribute('display_name_singular'))

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i> {{ __('voyager::generic.viewing') }}
        {{ ucfirst($dataType->getTranslatedAttribute('display_name_singular')) }} &nbsp;

        @can('edit', $dataTypeContent)
            <a href="{{ route('voyager.' . $dataType->slug . '.edit', $dataTypeContent->getKey()) }}" class="btn btn-info">
                <i class="glyphicon glyphicon-pencil"></i>
                <medium class="hidden-xs hidden-sm">{{ __('voyager::generic.edit') }}</medium>
            </a>
        @endcan
        @can('delete', $dataTypeContent)
            @if ($isSoftDeleted)
                <a href="{{ route('voyager.' . $dataType->slug . '.restore', $dataTypeContent->getKey()) }}"
                    title="{{ __('voyager::generic.restore') }}" class="btn btn-default restore"
                    data-id="{{ $dataTypeContent->getKey() }}" id="restore-{{ $dataTypeContent->getKey() }}">
                    <i class="voyager-trash"></i>
                    <medium class="hidden-xs hidden-sm">{{ __('voyager::generic.restore') }}</medium>
                </a>
            @else
                <a href="javascript:;" title="{{ __('voyager::generic.delete') }}" class="btn btn-danger delete"
                    data-id="{{ $dataTypeContent->getKey() }}" id="delete-{{ $dataTypeContent->getKey() }}">
                    <i class="voyager-trash"></i>
                    <medium class="hidden-xs hidden-sm">{{ __('voyager::generic.delete') }}</medium>
                </a>
            @endif
        @endcan
        @can('browse', $dataTypeContent)
            <a href="{{ route('voyager.' . $dataType->slug . '.index') }}" class="btn btn-warning">
                <i class="glyphicon glyphicon-list"></i>
                <medium class="hidden-xs hidden-sm">{{ __('voyager::generic.return_to_list') }}</medium>
            </a>
        @endcan
    </h1>
    @include('voyager::multilingual.language-selector')
@stop

@php
    $tabVisualization = $dataTypeContent;
@endphp

@section('content')
    <div class="page-content compass container-fluid">
        <ul class="nav nav-tabs">
            <li class="active">
                <a data-toggle="tab" href="#tab{{ $tabVisualization->id }}">
                    <i class="@if (!$tabVisualization->habilitado) voyager-x @else voyager-check @endif">
                    </i>{{ $tabVisualization->nombre_tab }}</a>
            </li>
        </ul>
        <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" class="tab-pane" id="tab{{ $tabVisualization->id }}">

                {{-- INICIA VISUALIZACION --}}
                @php
                    $renderData =
                        !is_null($tabVisualization) &&
                        !is_null($tabVisualization->data_json) &&
                        $tabVisualization->data_json != '' &&
                        $tabVisualization->data_json != '{}';

                    if ($renderData) {
                        $array = json_decode($tabVisualization->data_json, true);
                        $firstRow = array_keys(reset($array));
                        $rows = [];
                        foreach ($array as $establecimiento => $meses) {
                            $rows[] = array_merge([$establecimiento], array_values($meses));
                        }
                    }
                @endphp

                <x-panel-component col=12 panel="action">
                    <x-slot name="title">{{ $tabVisualization->nombre_tab ?? '' }}</x-slot>
                    <x-slot name="body">
                        <div style="margin: 1%">{!! $tabVisualization->texto_enriquecido ?? '' !!}</div>
                        <div style="text-align: center;">{!! $tabVisualization->codigo_incrustado ?? '' !!}</div>
                        @if ($renderData)
                            <x-data-visualization-component :data="$tabVisualization->data_json" :chartType="$tabVisualization->tipo_grafico" />
                            @if ($tabVisualization->muestra_tabla)
                                <div class="table-responsive" style="margin:2%; border:#EAEAEA solid 1px; overflow-x:auto;">
                                    <table id="dataTable" class="table table-condensed">
                                        <thead>
                                            <tr>
                                                <th>&nbsp;</th>
                                                @foreach ($firstRow as $column)
                                                    <th class="text-nowrap" style="min-width: 100px;">
                                                        {{ $column }}
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rows as $row)
                                                <tr>
                                                    @foreach ($row as $column)
                                                        <td><span>{{ $column }}</span></td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif
                    </x-slot>
                </x-panel-component>
            </div>
        </div>
    </div>

    {{-- Single delete modal --}}
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
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@stop

@section('javascript')
    @if ($isModelTranslatable)
        <script>
            $(document).ready(function() {
                $('.side-body').multilingual();
            });
        </script>
    @endif
    <script>
        var deleteFormAction;
        $('.delete').on('click', function(e) {
            var form = $('#delete_form')[0];

            if (!deleteFormAction) {
                // Save form action initial value
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
