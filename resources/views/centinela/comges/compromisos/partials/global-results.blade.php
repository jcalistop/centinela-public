@if ($globalResults && ($globalResults['mode'] ?? null) === 'score')
    <x-panel-component col='12' panel="warning">
        <x-slot name="title">
            Resultados globales por corte para el año {{ Helper::showCurrentYear()->ano }}
        </x-slot>
        <x-slot name="body">
            <style>
                .global-results-table .semaforo {
                    width: 5.5rem;
                    height: 2.4rem;
                    line-height: 2.3rem;
                    font-size: 0.9em;
                }
            </style>
            @php
                $totalPuntajeMaximo = 0;
                $totalAutoevaluacion = null;
                $totalEvaluacionPreliminar = null;
                $totalEvaluacionFinal = null;

                foreach ($globalResults['cutoffs'] as $cutoffId => $cutoff) {
                    $totalPuntajeMaximo += $globalResults['puntaje_maximo'][$cutoffId] ?? 0;
                    $v = $globalResults['autoevaluacion'][$cutoffId] ?? null;
                    if (!is_null($v)) {
                        $totalAutoevaluacion = ($totalAutoevaluacion ?? 0) + $v;
                    }
                    $v = $globalResults['evaluacion_preliminar'][$cutoffId] ?? null;
                    if (!is_null($v)) {
                        $totalEvaluacionPreliminar = ($totalEvaluacionPreliminar ?? 0) + $v;
                    }
                    $v = $globalResults['evaluacion_final'][$cutoffId] ?? null;
                    if (!is_null($v)) {
                        $totalEvaluacionFinal = ($totalEvaluacionFinal ?? 0) + $v;
                    }
                }

                $puntajeTexto = function (?float $valor): string {
                    if (is_null($valor)) {
                        return 'S/I';
                    }

                    return number_format($valor, 2);
                };

                $puntajeMaximoTexto = fn(float $valor): string => number_format($valor, 2);

                $pctCelda = function (?float $valor, float $maximo): string {
                    if (is_null($valor) || $maximo <= 0) {
                        return \Helper::muestra_semaforo(null, 75);
                    }

                    return \Helper::muestra_semaforo(($valor / $maximo) * 100, 75);
                };
            @endphp

            <x-comges.semaforo-legend :useScoreScale="true" />

            <div style="overflow-x:auto;">
                <table class="table table-bordered table-results text-center global-results-table">
                    <thead>
                        <tr>
                            <th class="text-left" rowspan="2">Corte</th>
                            <th rowspan="2">Puntaje máximo</th>
                            <th colspan="2">Autoevaluación</th>
                            <th colspan="2">Eval. preliminar</th>
                            <th colspan="2">Eval. final</th>
                        </tr>
                        <tr>
                            <th>Puntaje</th>
                            <th>Porcentaje</th>
                            <th>Puntaje</th>
                            <th>Porcentaje</th>
                            <th>Puntaje</th>
                            <th>Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($globalResults['cutoffs'] as $cutoffId => $cutoff)
                            @php
                                $max = $globalResults['puntaje_maximo'][$cutoffId] ?? 0;
                                $auto = $globalResults['autoevaluacion'][$cutoffId] ?? null;
                                $prelim = $globalResults['evaluacion_preliminar'][$cutoffId] ?? null;
                                $final = $globalResults['evaluacion_final'][$cutoffId] ?? null;
                            @endphp
                            <tr>
                                <td class="text-left"><strong>{{ $cutoff->nombre_corte }}</strong></td>
                                <td>{{ $puntajeMaximoTexto($max) }}</td>
                                <td>{{ $puntajeTexto($auto) }}</td>
                                <td>{!! $pctCelda($auto, $max) !!}</td>
                                <td>{{ $puntajeTexto($prelim) }}</td>
                                <td>{!! $pctCelda($prelim, $max) !!}</td>
                                <td>{{ $puntajeTexto($final) }}</td>
                                <td>{!! $pctCelda($final, $max) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if (count($globalResults['cutoffs'] ?? []) > 1)
                        <tfoot>
                            <tr style="background-color:#fdf9ef;">
                                <td class="text-left"><strong>Cumplimiento total</strong></td>
                                <td>{{ $puntajeMaximoTexto($totalPuntajeMaximo) }}</td>
                                <td>{!! \Helper::muestra_puntaje($totalAutoevaluacion) !!}</td>
                                <td>{!! $pctCelda($totalAutoevaluacion, $totalPuntajeMaximo) !!}</td>
                                <td>{!! \Helper::muestra_puntaje($totalEvaluacionPreliminar) !!}</td>
                                <td>{!! $pctCelda($totalEvaluacionPreliminar, $totalPuntajeMaximo) !!}</td>
                                <td>{!! \Helper::muestra_puntaje($totalEvaluacionFinal) !!}</td>
                                <td>{!! $pctCelda($totalEvaluacionFinal, $totalPuntajeMaximo) !!}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </x-slot>
        <x-slot name="footer">
            <div class="text-center">
                <a href="{{ route('descarga-evaluaciones-comges') }}" class="btn btn-sm btn-primary compromisos">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="12px" height="12px"
                        fill="#FFFFFF" style="margin-top: 0.3rem">
                        <path
                            d="M 28.8125 0.03125 L 0.8125 5.34375 C 0.339844 5.433594 0 5.863281 0 6.34375 L 0 43.65625 C 0 44.136719 0.339844 44.566406 0.8125 44.65625 L 28.8125 49.96875 C 28.875 49.980469 28.9375 50 29 50 C 29.230469 50 29.445313 49.929688 29.625 49.78125 C 29.855469 49.589844 30 49.296875 30 49 L 30 1 C 30 0.703125 29.855469 0.410156 29.625 0.21875 C 29.394531 0.0273438 29.105469 -0.0234375 28.8125 0.03125 Z M 32 6 L 32 13 L 34 13 L 34 15 L 32 15 L 32 20 L 34 20 L 34 22 L 32 22 L 32 27 L 34 27 L 34 29 L 32 29 L 32 35 L 34 35 L 34 37 L 32 37 L 32 44 L 47 44 C 48.101563 44 49 43.101563 49 42 L 49 8 C 49 6.898438 48.101563 6 47 6 Z M 36 13 L 44 13 L 44 15 L 36 15 Z M 6.6875 15.6875 L 11.8125 15.6875 L 14.5 21.28125 C 14.710938 21.722656 14.898438 22.265625 15.0625 22.875 L 15.09375 22.875 C 15.199219 22.511719 15.402344 21.941406 15.6875 21.21875 L 18.65625 15.6875 L 23.34375 15.6875 L 17.75 24.9375 L 23.5 34.375 L 18.53125 34.375 L 15.28125 28.28125 C 15.160156 28.054688 15.035156 27.636719 14.90625 27.03125 L 14.875 27.03125 C 14.8125 27.316406 14.664063 27.761719 14.4375 28.34375 L 11.1875 34.375 L 6.1875 34.375 L 12.15625 25.03125 Z M 36 20 L 44 20 L 44 22 L 36 22 Z M 36 27 L 44 27 L 44 29 L 36 29 Z M 36 35 L 44 35 L 44 37 L 36 37 Z" />
                    </svg>
                    Descargar Evaluaciones
                </a>
            </div>
        </x-slot>
    </x-panel-component>
@elseif (!empty($globalResults['cutoffs'] ?? []))
    <x-panel-component col='12' panel="warning">
        <x-slot name="title">
            Resultados globales por corte para el año {{ Helper::showCurrentYear()->ano }}
        </x-slot>
        <x-slot name="body">
            <table class="table table-bordered table-results">
                <thead>
                    <tr>
                        <th>Corte</th>
                        <th>Puntaje máximo</th>
                        <th>Auto-evaluacion</th>
                        <th>Evaluacion preliminar</th>
                        <th>Evaluacion final</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($globalResults['cutoffs'] as $cutoffId => $cutoff)
                        <tr>
                            <td>{{ $cutoff->clave }}</td>
                            <td>{{ number_format($globalResults['puntaje_maximo'][$cutoffId] ?? 0, 2, '.', '') }}</td>
                            <td>{{ number_format($globalResults['autoevaluacion'][$cutoffId] ?? 0, 2, '.', '') }}</td>
                            <td>{{ number_format($globalResults['evaluacion_preliminar'][$cutoffId] ?? 0, 2, '.', '') }}
                            </td>
                            <td>{{ number_format($globalResults['evaluacion_final'][$cutoffId] ?? 0, 2, '.', '') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-slot>
        <x-slot name="footer">
            <div class="text-center">
                <a href="{{ route('descarga-evaluaciones-comges') }}" class="btn btn-sm btn-primary compromisos">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="12px" height="12px"
                        fill="#FFFFFF" style="margin-top: 0.3rem">
                        <path
                            d="M 28.8125 0.03125 L 0.8125 5.34375 C 0.339844 5.433594 0 5.863281 0 6.34375 L 0 43.65625 C 0 44.136719 0.339844 44.566406 0.8125 44.65625 L 28.8125 49.96875 C 28.875 49.980469 28.9375 50 29 50 C 29.230469 50 29.445313 49.929688 29.625 49.78125 C 29.855469 49.589844 30 49.296875 30 49 L 30 1 C 30 0.703125 29.855469 0.410156 29.625 0.21875 C 29.394531 0.0273438 29.105469 -0.0234375 28.8125 0.03125 Z M 32 6 L 32 13 L 34 13 L 34 15 L 32 15 L 32 20 L 34 20 L 34 22 L 32 22 L 32 27 L 34 27 L 34 29 L 32 29 L 32 35 L 34 35 L 34 37 L 32 37 L 32 44 L 47 44 C 48.101563 44 49 43.101563 49 42 L 49 8 C 49 6.898438 48.101563 6 47 6 Z M 36 13 L 44 13 L 44 15 L 36 15 Z M 6.6875 15.6875 L 11.8125 15.6875 L 14.5 21.28125 C 14.710938 21.722656 14.898438 22.265625 15.0625 22.875 L 15.09375 22.875 C 15.199219 22.511719 15.402344 21.941406 15.6875 21.21875 L 18.65625 15.6875 L 23.34375 15.6875 L 17.75 24.9375 L 23.5 34.375 L 18.53125 34.375 L 15.28125 28.28125 C 15.160156 28.054688 15.035156 27.636719 14.90625 27.03125 L 14.875 27.03125 C 14.8125 27.316406 14.664063 27.761719 14.4375 28.34375 L 11.1875 34.375 L 6.1875 34.375 L 12.15625 25.03125 Z M 36 20 L 44 20 L 44 22 L 36 22 Z M 36 27 L 44 27 L 44 29 L 36 29 Z M 36 35 L 44 35 L 44 37 L 36 37 Z" />
                    </svg>
                    Descargar Evaluaciones
                </a>
            </div>
        </x-slot>
    </x-panel-component>
@endif
