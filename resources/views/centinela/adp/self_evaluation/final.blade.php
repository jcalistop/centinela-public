@extends('voyager::master')

@section('page_title', 'ADP — Evaluación final')

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $indicatorType->icon_class }}"></i>
        Final — periodo {{ $period->sequence }}
    </h1>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <p>
            <a href="{{ route('adp.self_evaluation.agreement', $agreement) }}">← Periodos del convenio</a>
            |
            <a href="{{ route('adp.self_evaluation.preliminary', [$agreement, $period]) }}">Preliminar</a>
            |
            <a href="{{ route('adp.self_evaluation.appeals', [$agreement, $period]) }}">Apelaciones</a>
        </p>

        @if (session('flash_final'))
            <div class="alert alert-success">{{ session('flash_final') }}</div>
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
                <p class="panel-title">Publicar evaluación final</p>
            </div>
            <div class="panel-body">
                <p class="text-muted">Si no hay preliminar, debe documentar el motivo en el campo inferior (publicación
                    manual auditada).</p>
                <form method="post" action="{{ route('adp.self_evaluation.final.publish', [$agreement, $period]) }}">
                    @csrf
                    <div class="form-group">
                        <label>Motivo si publica sin preliminar (opcional si ya existe preliminar)</label>
                        <textarea name="without_preliminary_reason" class="form-control" rows="2">{{ old('without_preliminary_reason') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"
                        onclick="return confirm('¿Publicar una nueva versión de evaluación final?');">Publicar
                        final</button>
                </form>
            </div>
        </div>

        @if ($final !== null)
            <div class="panel panel-bordered m-t-20">
                <div class="panel-heading">
                    <p class="panel-title">Última versión publicada (v.{{ $final->version }})</p>
                </div>
                <div class="panel-body">
                    <p>
                        <strong>Global:</strong>
                        {{ $final->weighted_global_percent !== null ? $final->weighted_global_percent . '%' : '—' }}
                        @if ($final->published_without_preliminary)
                            <span class="label label-warning m-l-10">Sin preliminar</span>
                        @endif
                    </p>
                    @if ($final->published_without_preliminary && $final->published_without_preliminary_reason)
                        <p><strong>Motivo documentado:</strong> {{ $final->published_without_preliminary_reason }}</p>
                    @endif
                    @if ($final->preliminarySnapshot)
                        <p><strong>Preliminar de referencia:</strong> versión {{ $final->preliminarySnapshot->version }}
                        </p>
                    @endif

                    <h4>Filas por indicador</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Indicador</th>
                                <th>Preliminar %</th>
                                <th>Final %</th>
                                <th>Override</th>
                                <th>Cambiar final (override)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($final->rows as $row)
                                <tr>
                                    <td>{{ $row->indicator->clave }} — {{ $row->indicator->nombre }}</td>
                                    <td>{{ $row->preliminary_compliance_percent ?? '—' }}</td>
                                    <td>{{ $row->final_compliance_percent ?? '—' }}</td>
                                    <td>{{ $row->is_overridden ? 'Sí' : 'No' }}</td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-default" data-toggle="collapse"
                                            data-target="#ov-{{ $row->id }}">Override</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="ov-{{ $row->id }}">
                                    <td colspan="5">
                                        <form method="post"
                                            action="{{ route('adp.self_evaluation.final.override', [$agreement, $period]) }}">
                                            @csrf
                                            <input type="hidden" name="adp_indicator_id" value="{{ $row->indicator_id }}">
                                            <div class="form-group">
                                                <label>Nuevo cumplimiento % (final)</label>
                                                <input type="text" name="new_compliance_percent" class="form-control"
                                                    value="{{ old('new_compliance_percent') }}" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Justificación (obligatoria)</label>
                                                <textarea name="reason" class="form-control" rows="2" required>{{ old('reason') }}</textarea>
                                            </div>
                                            <button type="submit" class="btn btn-warning btn-sm">Registrar
                                                override</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($final->overrides->isNotEmpty())
                        <h4>Historial de overrides</h4>
                        <table class="table table-condensed">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Indicador</th>
                                    <th>Anterior</th>
                                    <th>Nuevo</th>
                                    <th>Usuario</th>
                                    <th>Razón</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($final->overrides as $o)
                                    <tr>
                                        <td>{{ $o->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>{{ $o->indicator_id }}</td>
                                        <td>{{ $o->previous_value ?? '—' }}</td>
                                        <td>{{ $o->new_value ?? '—' }}</td>
                                        <td>{{ $o->user?->name ?? '—' }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($o->reason, 80) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @else
            <p class="text-muted m-t-20">Aún no hay evaluación final publicada.</p>
        @endif
    </div>
@stop
