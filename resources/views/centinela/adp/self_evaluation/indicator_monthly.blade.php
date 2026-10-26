@extends('voyager::master')

@section('page_title', 'ADP — Carga mensual')

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $indicatorType->icon_class }}"></i>
        {{ $indicator->nombre }}
    </h1>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <p>
            <a href="{{ route('adp.self_evaluation.agreement', $agreement) }}">← Periodos del convenio</a>
        </p>

        @if (session('flash_monthly'))
            <div class="alert alert-success">{{ session('flash_monthly') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="panel panel-bordered m-t-20">
            <div class="panel-heading">
                <p class="panel-title">Periodo {{ $period->sequence }} ({{ $period->start_date?->format('d/m/Y') }} —
                    {{ $period->end_date?->format('d/m/Y') }})</p>
            </div>
            <div class="panel-body">
                <p><strong>Indicador:</strong> {{ $indicator->clave }} — {{ $indicator->nombre }}</p>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Mes (referencia)</th>
                            <th>Valor</th>
                            <th>Observación</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthStarts as $m)
                            @php
                                $key = $m->format('Y-m-d');
                                $row = $rows->get($key);
                            @endphp
                            <tr>
                                <td>{{ $m->format('Y-m') }}</td>
                                <td colspan="3">
                                    <form method="post"
                                        action="{{ route('adp.self_evaluation.indicator.monthly.store', [$agreement, $period, $indicator]) }}"
                                        class="form-inline">
                                        @csrf
                                        <input type="hidden" name="month_date" value="{{ $m->format('Y-m-d') }}">
                                        <div class="form-group">
                                            <input type="text" name="valor" class="form-control"
                                                value="{{ $row?->valor }}"
                                                placeholder="Valor">
                                        </div>
                                        <div class="form-group">
                                            <input type="text" name="observacion" class="form-control"
                                                value="{{ $row?->observacion }}"
                                                placeholder="Observación" style="min-width:220px">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
