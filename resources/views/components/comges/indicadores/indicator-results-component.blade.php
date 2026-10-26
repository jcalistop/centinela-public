<div class="table-responsive">
    <style>
        .table-results .componente-corte-alerta {
            border-left: 4px solid #f0ad4e;
            padding: 0.35rem 0.35rem 0.35rem 0.5rem;
            margin: -0.25rem -0.5rem;
            background-color: #fcf8f3;
        }

        .table-results .componente-corte-alerta .glyphicon {
            margin-right: 0.35rem;
        }
    </style>
    <x-comges.semaforo-legend :useScoreScale="true" />
    <table class="table table-bordered table-results">
        <caption>
            <h5>Resultados del indicador</h5>
        </caption>
        <thead>
            <tr>
                <th>Componente</th>
                <th>Ponderación</th>
                <th>Autoevaluación</th>
                <th>Evaluación preliminar</th>
                <th>Evaluación final</th>
                <th>Promedio país</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumPonderacion = 0;
                $sumPuntajeAuto = 0;
                $sumPuntajePreliminar = 0;
                $sumPuntajeFinal = 0;
            @endphp
            @foreach ($dataTypeContent->comIndicatorCutoffs as $comIndicatorCutoff)
                @php
                    $ponderacionCorte = $comIndicatorCutoff->ponderacion_corte / 100;
                    $query = $comIndicatorCutoff->comActions->first();
                    if ($query && !$query->comEvaluations->isEmpty()) {
                        $evaluationsScope = $query->comEvaluations->where(
                            'cen_hie_dependency_id',
                            Auth::user()->cen_hie_dependency_id,
                        );
                        $evaAuto = $evaluationsScope
                            ->where('tipo_evaluacion', \App\Enums\Comges\EvaluacionTipo::Autoevaluacion)
                            ->first();
                        $evaPreliminar = $evaluationsScope
                            ->where('tipo_evaluacion', \App\Enums\Comges\EvaluacionTipo::Preliminar)
                            ->first();
                        $evaFinal = $evaluationsScope
                            ->where('tipo_evaluacion', \App\Enums\Comges\EvaluacionTipo::Final)
                            ->first();
                    } else {
                        $evaAuto = null;
                        $evaPreliminar = null;
                        $evaFinal = null;
                    }

                    $actionsPonderacion = $comIndicatorCutoff->comActions
                        ->map(fn($action) => (float) ($action->ponderacion_accion ?? 0))
                        ->sum();
                    $actionsPonderacionRounded = round($actionsPonderacion, 2);
                    $showPonderacionAlert = in_array(Auth::user()->role->name, ['parametrizador', 'admin'], true);
                    if ($comIndicatorCutoff->cutoff->clave !== \App\Enums\Corte\CutoffClave::Apelacion) {
                        $sumPuntajeAuto =
                            is_null($sumPuntajeAuto) || is_null(optional($evaAuto)->cumplimiento)
                                ? null
                                : $sumPuntajeAuto + $evaAuto->cumplimiento * $ponderacionCorte;
                        $sumPuntajePreliminar =
                            is_null($sumPuntajePreliminar) || is_null(optional($evaPreliminar)->cumplimiento)
                                ? null
                                : $sumPuntajePreliminar + $evaPreliminar->cumplimiento * $ponderacionCorte;
                        $sumPuntajeFinal =
                            is_null($sumPuntajeFinal) || is_null(optional($evaFinal)->cumplimiento)
                                ? null
                                : $sumPuntajeFinal + $evaFinal->cumplimiento * $ponderacionCorte;
                        $sumPonderacion += $comIndicatorCutoff->ponderacion_corte;
                    }
                @endphp
                @if ($comIndicatorCutoff->cutoff->clave === \App\Enums\Corte\CutoffClave::Apelacion)
                    <tr>
                        <th nowrap>
                            @if ($showPonderacionAlert && $actionsPonderacionRounded != 100.0)
                                <div class="componente-corte-alerta"
                                    title="La suma de ponderaciones de las acciones para este corte debe ser 100%.">
                                    <span class="glyphicon glyphicon-exclamation-sign text-warning"
                                        aria-hidden="true"></span>
                                    <strong>Resultado</strong>
                                    <div class="small text-muted">Ponderación acciones:
                                        {{ $actionsPonderacionRounded }}% (debe ser 100%)</div>
                                </div>
                            @else
                                Resultado
                            @endif
                        </th>
                        <td>{{ $sumPonderacion . '%' }}</td>
                        <td>{!! \Helper::muestra_puntaje($sumPuntajeAuto) !!}</td>
                        <td>{!! \Helper::muestra_puntaje($sumPuntajePreliminar) !!}</td>
                        <td>{!! \Helper::muestra_puntaje($sumPuntajeFinal) !!}</td>
                        <td></td>
                    </tr>
                    @continue
                @endif
                <tr>
                    <th nowrap>
                        @if ($showPonderacionAlert && $actionsPonderacionRounded != 100.0)
                            <div class="componente-corte-alerta"
                                title="La suma de ponderaciones de las acciones para este corte debe ser 100%.">
                                <span class="glyphicon glyphicon-exclamation-sign text-warning"
                                    aria-hidden="true"></span>
                                <strong>{{ $comIndicatorCutoff->cutoff->nombre_corte }}</strong>
                                <div class="small text-muted">Ponderación acciones:
                                    {{ $actionsPonderacionRounded }}% (debe ser 100%)</div>
                            </div>
                        @else
                            {{ $comIndicatorCutoff->cutoff->nombre_corte }}
                        @endif
                    </th>
                    <td>{{ round($comIndicatorCutoff->ponderacion_corte, 0) . '%' }}</td>
                    <td>{!! \Helper::muestra_puntaje($evaAuto->cumplimiento ?? null) !!}</td>
                    <td>{!! \Helper::muestra_puntaje($evaPreliminar->cumplimiento ?? null) !!}</td>
                    <td>{!! \Helper::muestra_puntaje($evaFinal->cumplimiento ?? null) !!}</td>
                    <td>{{ $comIndicatorCutoff->promedio_pais ? $comIndicatorCutoff->promedio_pais . ' %' : '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
