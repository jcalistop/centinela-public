@extends('voyager::master')

@section('page_title', 'ADP — Periodos del convenio')

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $indicatorType->icon_class }}"></i>
        Convenio #{{ $agreement->id }}
    </h1>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <p>
            <a href="{{ route('adp.self_evaluation.agreements') }}">← Convenios</a>
            |
            <a href="{{ route('adp.instrument.index') }}">Hub ADP</a>
        </p>

        @if (session('flash_calc'))
            <div class="alert alert-success">{{ session('flash_calc') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="m-b-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="panel panel-bordered m-t-20">
            <div class="panel-heading">
                <p class="panel-title">Periodos anuales</p>
            </div>
            <div class="panel-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Sec.</th>
                            <th>Inicio</th>
                            <th>Término</th>
                            <th>Evaluación parcial (6 meses)</th>
                            <th>Preliminar / apelaciones / final</th>
                            <th>Indicadores — carga mensual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agreement->periods as $p)
                            <tr>
                                <td>{{ $p->sequence }}</td>
                                <td>{{ $p->start_date?->format('d/m/Y') }}</td>
                                <td>{{ $p->end_date?->format('d/m/Y') }}</td>
                                <td>
                                    <a href="{{ route('adp.self_evaluation.midterm', [$agreement, $p]) }}"
                                        class="btn btn-sm btn-default">Registrar / ver</a>
                                    <form method="post" action="{{ route('adp.self_evaluation.period.recalculate', [$agreement, $p]) }}"
                                        style="display:inline" onsubmit="return confirm('¿Recalcular resultados de este periodo?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning">Recalcular</button>
                                    </form>
                                </td>
                                <td>
                                    <a href="{{ route('adp.self_evaluation.preliminary', [$agreement, $p]) }}"
                                        class="btn btn-sm btn-default">Preliminar</a>
                                    <a href="{{ route('adp.self_evaluation.appeals', [$agreement, $p]) }}"
                                        class="btn btn-sm btn-default">Apelaciones</a>
                                    <a href="{{ route('adp.self_evaluation.final', [$agreement, $p]) }}"
                                        class="btn btn-sm btn-default">Final</a>
                                </td>
                                <td>
                                    <ul class="list-unstyled">
                                        @foreach ($p->objectives as $obj)
                                            @foreach ($obj->indicators as $ind)
                                                <li>
                                                    <a
                                                        href="{{ route('adp.self_evaluation.indicator.monthly', [$agreement, $p, $ind]) }}">{{ $ind->clave }}
                                                        — {{ $ind->nombre }}</a>
                                                </li>
                                            @endforeach
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
