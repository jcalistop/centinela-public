@extends('voyager::master')

@section('page_title', 'ADP — Evaluación parcial (6 meses)')

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $indicatorType->icon_class }}"></i>
        Evaluación parcial — periodo {{ $period->sequence }}
    </h1>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <p>
            <a href="{{ route('adp.self_evaluation.agreement', $agreement) }}">← Periodos del convenio</a>
        </p>

        @if (session('flash_midterm'))
            <div class="alert alert-success">{{ session('flash_midterm') }}</div>
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
                <p class="panel-title">Ventana esperada (inicio del periodo + 6 meses ±
                    {{ \App\Services\Adp\AdpMidtermEvaluationValidator::TOLERANCE_DAYS }} días)</p>
            </div>
            <div class="panel-body">
                <p><strong>Periodo:</strong> {{ $period->start_date?->format('d/m/Y') }} —
                    {{ $period->end_date?->format('d/m/Y') }}</p>
                <p><strong>Fecha referencia 6 meses:</strong> {{ $expectedMid->format('d/m/Y') }}</p>

                @if ($evaluation)
                    <p class="text-muted">Ya existe un registro (estado: {{ $evaluation->status }}). Puede
                        actualizarlo.</p>
                @endif

                <form method="post" action="{{ route('adp.self_evaluation.midterm.store', [$agreement, $period]) }}">
                    @csrf
                    <div class="form-group">
                        <label for="evaluated_on">Fecha de evaluación</label>
                        <input type="date" name="evaluated_on" id="evaluated_on" class="form-control"
                            value="{{ old('evaluated_on', optional($evaluation)->evaluated_on?->format('Y-m-d') ?? $expectedMid->format('Y-m-d')) }}"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="observaciones">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" class="form-control" rows="4">{{ old('observaciones', $evaluation?->observaciones) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Estado</label>
                        <select name="status" id="status" class="form-control">
                            <option value="draft"
                                {{ old('status', $evaluation?->status ?? 'draft') === 'draft' ? 'selected' : '' }}>
                                Borrador</option>
                            <option value="submitted"
                                {{ old('status', $evaluation?->status) === 'submitted' ? 'selected' : '' }}>
                                Enviada</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            </div>
        </div>
    </div>
@stop
