@extends('voyager::master')

@section('css')

    @include('voyager::compass.includes.styles')

@stop

@section('page_title', 'Instrumentos Locales')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-angle-right"></i>
        {!! $dataTypeContent->menu_superior ? $dataTypeContent->menu_superior . '&nbsp;&nbsp;>&nbsp;&nbsp;' : '' !!} {{ $dataTypeContent->titulo }}
    </h1>
    @if ($dataTypeContent->texto_enriquecido)
        <div style="position: relative; z-index: 1; margin: 0 50px 0 50px;">{!! $dataTypeContent->texto_enriquecido !!}</div>
    @endif
@stop

@section('content')

    <div class="page-content compass container-fluid">
        @if ($dataTypeContent->tabVisualizations->isEmpty())
            <h3 class="text-center"><i class="voyager-lightbulb"></i>NO EXISTEN PESTAÑAS PARA ESTE INSTRUMENTO LOCAL</h3>
        @else
            <ul class="nav nav-tabs">
                @php $i = 0; @endphp
                @foreach ($dataTypeContent->tabVisualizations->sortBy('orden') as $tabVisualization)
                    @if (
                        $tabVisualization->habilitado ||
                            Auth::user()->role->name == 'admin' ||
                            Auth::user()->role->name == 'control-gestion')
                        @php $i++ @endphp
                        <li @if ($i == 1) class="active" @endif>
                            <a data-toggle="tab" href="#tab{{ $tabVisualization->id }}">
                                <i class="@if (!$tabVisualization->habilitado) voyager-x @else voyager-check @endif">

                                </i>{{ $tabVisualization->nombre_tab }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>
            <div class="tab-content">
                @php $i = 0; @endphp
                @foreach ($dataTypeContent->tabVisualizations->sortBy('orden') as $tabVisualization)
                    @if (
                        $tabVisualization->habilitado ||
                            Auth::user()->role->name == 'admin' ||
                            Auth::user()->role->name == 'control-gestion')
                        @php $i++ @endphp
                        <div role="tabpanel"
                            @if ($i == 1) class="tab-pane active" @else class="tab-pane" @endif
                            id="tab{{ $tabVisualization->id }}">

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
                                            <div class="table-responsive"
                                                style="margin:2%; border:#EAEAEA solid 1px; overflow-x:auto;">
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
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@stop

@section('css')
@stop

@section('javascript')

@stop
