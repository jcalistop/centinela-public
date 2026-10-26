@extends('voyager::master')

@section('page_title', 'ADP — Evaluación preliminar')

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $indicatorType->icon_class }}"></i>
        Preliminar — periodo {{ $period->sequence }}
    </h1>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <p>
            <a href="{{ route('adp.self_evaluation.agreement', $agreement) }}">← Periodos del convenio</a>
            |
            <a href="{{ route('adp.self_evaluation.appeals', [$agreement, $period]) }}">Apelaciones</a>
            |
            <a href="{{ route('adp.self_evaluation.final', [$agreement, $period]) }}">Evaluación final</a>
        </p>

        @if (session('flash_preliminary'))
            <div class="alert alert-success">{{ session('flash_preliminary') }}</div>
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
                <p class="panel-title">Snapshot de resultados calculados</p>
            </div>
            <div class="panel-body">
                @if ($snapshot === null)
                    <p class="text-muted">Aún no hay evaluación preliminar. Genere un snapshot después de recalcular el periodo.</p>
                    <form method="post" action="{{ route('adp.self_evaluation.preliminary.store', [$agreement, $period]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Generar preliminar</button>
                    </form>
                @else
                    <p>
                        <strong>Versión:</strong> {{ $snapshot->version }}
                        <span class="m-l-15"><strong>Global (snapshot):</strong>
                            {{ $snapshot->weighted_global_percent !== null ? $snapshot->weighted_global_percent . '%' : '—' }}</span>
                    </p>
                    <form method="post" action="{{ route('adp.self_evaluation.preliminary.store', [$agreement, $period]) }}"
                        class="m-b-20" onsubmit="return confirm('¿Generar una nueva versión de preliminar?');">
                        @csrf
                        <button type="submit" class="btn btn-default btn-sm">Nueva versión de snapshot</button>
                    </form>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Indicador</th>
                                <th>Peso %</th>
                                <th>Resultado bruto</th>
                                <th>Cumplimiento %</th>
                                <th>Apelar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshot->rows as $row)
                                <tr>
                                    <td>{{ $row->indicator->clave }} — {{ $row->indicator->nombre }}</td>
                                    <td>{{ $row->weight_percent ?? '—' }}</td>
                                    <td>{{ $row->raw_result ?? '—' }}</td>
                                    <td>
                                        @if ($row->error_message)
                                            <span class="text-danger" title="{{ $row->error_message }}">Error</span>
                                        @else
                                            {{ $row->compliance_percent ?? '—' }}
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-warning" data-toggle="collapse"
                                            data-target="#appeal-{{ $row->id }}">Apelar</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="appeal-{{ $row->id }}">
                                    <td colspan="5">
                                        <form method="post"
                                            action="{{ route('adp.self_evaluation.appeal.store', [$agreement, $period, $row->indicator]) }}">
                                            @csrf
                                            <div class="form-group">
                                                <label>Motivo de la apelación</label>
                                                <textarea name="motivo" class="form-control" rows="3" required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm">Enviar apelación</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@stop
