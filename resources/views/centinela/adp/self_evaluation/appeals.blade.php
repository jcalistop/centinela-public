@extends('voyager::master')

@section('page_title', 'ADP — Apelaciones')

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $indicatorType->icon_class }}"></i>
        Apelaciones — periodo {{ $period->sequence }}
    </h1>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <p>
            <a href="{{ route('adp.self_evaluation.agreement', $agreement) }}">← Periodos del convenio</a>
            |
            <a href="{{ route('adp.self_evaluation.preliminary', [$agreement, $period]) }}">Preliminar</a>
            |
            <a href="{{ route('adp.self_evaluation.final', [$agreement, $period]) }}">Evaluación final</a>
        </p>

        @if (session('flash_appeal'))
            <div class="alert alert-success">{{ session('flash_appeal') }}</div>
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
                <p class="panel-title">Listado de apelaciones</p>
            </div>
            <div class="panel-body">
                @if ($appeals->isEmpty())
                    <p class="text-muted">No hay apelaciones registradas para este periodo.</p>
                @else
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Indicador</th>
                                <th>Estado</th>
                                <th>Snapshot v.</th>
                                <th>Motivo</th>
                                <th>Resolución</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($appeals as $a)
                                <tr>
                                    <td>{{ $a->indicator->clave }} — {{ $a->indicator->nombre }}</td>
                                    <td>{{ $a->status }}</td>
                                    <td>{{ $a->preliminarySnapshot?->version }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($a->motivo, 120) }}</td>
                                    <td>{{ $a->resolucion ? \Illuminate\Support\Str::limit($a->resolucion, 80) : '—' }}</td>
                                    <td>
                                        @if ($a->status === \App\Models\AdpIndicatorAppeal::STATUS_PENDING)
                                            <button type="button" class="btn btn-xs btn-primary" data-toggle="collapse"
                                                data-target="#resolve-{{ $a->id }}">Resolver</button>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                @if ($a->status === \App\Models\AdpIndicatorAppeal::STATUS_PENDING)
                                    <tr class="collapse" id="resolve-{{ $a->id }}">
                                        <td colspan="6">
                                            <form method="post"
                                                action="{{ route('adp.self_evaluation.appeal.resolve', [$agreement, $a]) }}">
                                                @csrf
                                                <div class="form-group">
                                                    <label>Resultado</label>
                                                    <select name="status" class="form-control" required>
                                                        <option value="accepted">Aceptada</option>
                                                        <option value="rejected">Rechazada</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Resolución (texto)</label>
                                                    <textarea name="resolucion" class="form-control" rows="3"
                                                        required></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-success btn-sm">Guardar
                                                    resolución</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@stop
