<div class="table-responsive">
    <style>
        .table-results .componente-corte-alerta {
            border-left: 4px solid #f0ad4e;
            padding: 0.35rem 0.35rem 0.35rem 0.5rem;
            margin: 0.35rem 0 0 0;
            background-color: #fcf8f3;
        }

        .table-results .componente-corte-alerta .glyphicon {
            margin-right: 0.35rem;
        }
    </style>
    <x-comges.semaforo-legend :useScoreScale="false" />
    <table class="table table-bordered table-results">
        <caption>
            <h5>Resultados del indicador</h5>
        </caption>
        <thead>
            <tr>
                <th>Corte</th>
                <th>Acción</th>
                <th>Ponderación</th>
                <th>Autoevaluación</th>
                <th>Evaluación preliminar</th>
                <th>Evaluación final</th>
                <th>Promedio país</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dataTypeContent->comIndicatorCutoffs as $comIndicatorCutoff)
                @foreach ($comIndicatorCutoff->comActions as $comAction)
                    @php
                        $actionsPonderacion = $comIndicatorCutoff->comActions
                            ->map(fn($action) => (float) ($action->ponderacion_accion ?? 0))
                            ->sum();
                        $actionsPonderacionRounded = round($actionsPonderacion, 2);
                        $showPonderacionAlert = in_array(Auth::user()->role->name, ['parametrizador', 'admin'], true);

                        $query = $comAction->comEvaluations->where(
                            'cen_hie_dependency_id',
                            Auth::user()->cen_hie_dependency_id,
                        );
                        $evaAuto = $query
                            ->where('tipo_evaluacion', \App\Enums\Comges\EvaluacionTipo::Autoevaluacion)
                            ->first();
                        $evaPreliminar = $query
                            ->where('tipo_evaluacion', \App\Enums\Comges\EvaluacionTipo::Preliminar)
                            ->first();
                        $evaFinal = $query->where('tipo_evaluacion', \App\Enums\Comges\EvaluacionTipo::Final)->first();
                    @endphp
                    <tr>
                        @if ($loop->first)
                            <th nowrap rowspan="{{ $comIndicatorCutoff->comActions->count() }}">
                                <strong>{{ $comIndicatorCutoff->cutoff->nombre_corte }}</strong>
                                <br>({{ round($comIndicatorCutoff->ponderacion_corte, 0) }}%)
                                @if ($showPonderacionAlert && $actionsPonderacionRounded != 100.0)
                                    <div class="componente-corte-alerta"
                                        title="La suma de ponderaciones de las acciones para este corte debe ser 100%.">
                                        <span class="glyphicon glyphicon-exclamation-sign text-warning"
                                            aria-hidden="true"></span>
                                        <strong>Ponderación acciones</strong>
                                        <div class="small text-muted">{{ $actionsPonderacionRounded }}% (debe ser 100%)
                                        </div>
                                    </div>
                                @endif
                            </th>
                        @endif
                        <td>{{ $comAction->numero }}</td>
                        <td>{{ $comAction->ponderacion_accion . '%' }}</td>
                        <td>{!! \Helper::muestra_semaforo($evaAuto?->cumplimiento, 75) !!}</td>
                        @if (!$comIndicatorCutoff->evaluacion_local)
                            <td>{!! \Helper::muestra_semaforo($evaPreliminar?->cumplimiento, 75) !!}</td>
                            <td>{!! \Helper::muestra_semaforo($evaFinal?->cumplimiento, 75) !!}</td>
                            <td>{{ $comIndicatorCutoff->promedio_pais ? $comIndicatorCutoff->promedio_pais . ' %' : '' }}
                            </td>
                        @else
                            <td>No aplica</td>
                            <td>No aplica</td>
                            <td>No aplica</td>
                        @endif
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
