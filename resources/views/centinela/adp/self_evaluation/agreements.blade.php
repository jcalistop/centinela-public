@extends('voyager::master')

@section('page_title', 'ADP — Autoevaluación')

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $indicatorType->icon_class }}"></i>
        Autoevaluación — convenios
    </h1>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <p><a href="{{ route('adp.instrument.index') }}">← Volver al hub ADP</a></p>

        <div class="panel panel-bordered">
            <div class="panel-heading">
                <p class="panel-title">Convenios (carga mensual y evaluación parcial 6 meses)</p>
            </div>
            <div class="panel-body">
                @if ($agreements->isEmpty())
                    <p>No hay convenios registrados para su Servicio de Salud.</p>
                @else
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Inicio convenio</th>
                                <th>Años</th>
                                <th>Asignación</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agreements as $agr)
                                <tr>
                                    <td>{{ $agr->id }}</td>
                                    <td>{{ $agr->agreement_start?->format('d/m/Y') }}</td>
                                    <td>{{ $agr->duration_years }}</td>
                                    <td>
                                        #{{ $agr->adp_position_assignment_id }}
                                        @if ($agr->relationLoaded('assignment') && $agr->assignment?->center)
                                            — {{ $agr->assignment->center->nombre_corto }}
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('adp.self_evaluation.agreement', $agr) }}"
                                            class="btn btn-sm btn-primary">Abrir</a>
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
