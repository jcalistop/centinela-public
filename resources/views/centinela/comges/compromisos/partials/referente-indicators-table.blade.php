@php
    $useScoreScale = $useScoreScale ?? \App\Helpers\Helper::comgesUsesScoreScale();
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\ComIndicator> $indicators */
    $indicators = \App\Models\ComIndicator::query()
        ->user()
        ->with(['comCommitment', 'comIndicatorCutoffs.cutoff', 'comIndicatorCutoffs.comActions.comEvaluations'])
        ->orderBy('orden')
        ->orderBy('clave')
        ->get();
@endphp

<style>
    .table-results .semaforo {
        width: 4rem;
    }

    .commitment-indicators-table {
        table-layout: fixed;
    }

    .table-results td.resultado-puntajes-destacados {
        background-color: #eef5fc;
        border-color: #d9e6f2;
        vertical-align: middle;
    }

    .commitment-indicators-table th.commitment-col-componente,
    .commitment-indicators-table td.commitment-col-componente {
        text-align: center;
        vertical-align: middle;
    }
</style>

@if ($indicators->isEmpty())
    <div class="text-center">
        <br>No existen registros para esta sección<br><br>
    </div>
@else
    <div class="panel panel-hidden">
        <div class="panel-body">
            <x-comges.semaforo-legend :useScoreScale="$useScoreScale" />
            <table class="table table-bordered table-results commitment-indicators-table">
                <colgroup>
                    <col style="width: 48%;">
                    <col style="width: 14%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                    <col style="width: 8rem;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Indicador</th>
                        <th class="commitment-col-componente">Componente</th>
                        <th>Autoevaluación</th>
                        <th>Evaluación preliminar</th>
                        <th>Evaluación final</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($indicators as $comIndicator)
                        @php
                            $cutoffs = $comIndicator->comIndicatorCutoffs;
                            $rowspan = max($cutoffs->count(), 1);
                        @endphp

                        @if ($cutoffs->isEmpty())
                            <tr>
                                <td class="text-left">
                                    <h4>{{ $comIndicator->clave }}:</h4> {{ $comIndicator->nombre }}
                                    @if (!is_null($comIndicator->ponderacion_indicador))
                                        <div class="small text-muted">
                                            Ponderación:
                                            {{ number_format((float) $comIndicator->ponderacion_indicador, 2, ',', '.') }}
                                            %
                                        </div>
                                    @endif
                                    @if ($comIndicator->comCommitment)
                                        <div class="small text-muted">
                                            Compromiso: {{ $comIndicator->comCommitment->clave }}
                                        </div>
                                    @endif
                                </td>
                                <td colspan="4"></td>
                                <td>
                                    <a href="{{ route('voyager.indicadores-comges.show', $comIndicator->id) }}"
                                        class="btn btn-sm btn-warning" style="width: 5rem;">
                                        <i class="voyager-eye"></i> Ver</a>
                                </td>
                            </tr>
                        @endif

                        @foreach ($cutoffs as $comIndicatorCutoff)
                            <tr>
                                @if ($loop->first)
                                    <td rowspan="{{ $rowspan }}" class="text-left">
                                        <h4>{{ $comIndicator->clave }}:</h4> {{ $comIndicator->nombre }}
                                        @if (!is_null($comIndicator->ponderacion_indicador))
                                            <div class="small text-muted">
                                                Ponderación:
                                                {{ number_format((float) $comIndicator->ponderacion_indicador, 2, ',', '.') }}
                                                %
                                            </div>
                                        @endif
                                        @if ($comIndicator->comCommitment)
                                            <div class="small text-muted">
                                                Compromiso: {{ $comIndicator->comCommitment->clave }}
                                            </div>
                                        @endif
                                    </td>
                                @endif

                                @if (
                                    $useScoreScale &&
                                        $comIndicatorCutoff->cutoff &&
                                        $comIndicatorCutoff->cutoff->clave === \App\Enums\Corte\CutoffClave::Apelacion)
                                    <td class="resultado-puntajes-destacados commitment-col-componente" nowrap>
                                        <strong>Resultado</strong>
                                    </td>
                                    <td class="resultado-puntajes-destacados">{!! \Helper::muestra_puntaje($comIndicator->puntajePonderado('AUTOEVALUACION')) !!}</td>
                                    <td class="resultado-puntajes-destacados">{!! \Helper::muestra_puntaje($comIndicator->puntajePonderado('PRELIMINAR')) !!}</td>
                                    <td class="resultado-puntajes-destacados">{!! \Helper::muestra_puntaje($comIndicator->puntajePonderado('FINAL')) !!}</td>
                                @else
                                    <td nowrap class="commitment-col-componente">
                                        {{ $comIndicatorCutoff->cutoff?->nombre_corte ?? '—' }}
                                        @if (!is_null($comIndicatorCutoff->ponderacion_corte))
                                            <div class="small text-muted">
                                                Ponderación:
                                                {{ number_format((float) $comIndicatorCutoff->ponderacion_corte, 2, ',', '.') }}
                                                %
                                            </div>
                                        @endif
                                    </td>
                                    @if ($useScoreScale)
                                        <td>{!! \Helper::muestra_puntaje($comIndicatorCutoff->promedioCumplimiento('AUTOEVALUACION')) !!}</td>
                                        @if (!$comIndicatorCutoff->evaluacion_local)
                                            <td>{!! \Helper::muestra_puntaje($comIndicatorCutoff->promedioCumplimiento('PRELIMINAR')) !!}</td>
                                            <td>{!! \Helper::muestra_puntaje($comIndicatorCutoff->promedioCumplimiento('FINAL')) !!}</td>
                                        @else
                                            <td></td>
                                            <td></td>
                                        @endif
                                    @else
                                        @php
                                            $evaAuto = collect();
                                            $evaPreliminar = collect();
                                            $evaFinal = collect();
                                            foreach ($comIndicatorCutoff->comActions as $comAction) {
                                                $evaluations = $comAction->comEvaluations;
                                                if (Auth::user()?->cen_hie_dependency_id) {
                                                    $evaluations = $evaluations->where(
                                                        'cen_hie_dependency_id',
                                                        Auth::user()->cen_hie_dependency_id,
                                                    );
                                                }
                                                $evaAuto[] = $evaluations->firstWhere(
                                                    'tipo_evaluacion',
                                                    \App\Enums\Comges\EvaluacionTipo::Autoevaluacion,
                                                )?->cumplimiento;
                                                $evaPreliminar[] = $evaluations->firstWhere(
                                                    'tipo_evaluacion',
                                                    \App\Enums\Comges\EvaluacionTipo::Preliminar,
                                                )?->cumplimiento;
                                                $evaFinal[] = $evaluations->firstWhere(
                                                    'tipo_evaluacion',
                                                    \App\Enums\Comges\EvaluacionTipo::Final,
                                                )?->cumplimiento;
                                            }
                                        @endphp
                                        <td>{!! \Helper::muestra_semaforo($evaAuto->whereNull()->isNotEmpty() ? null : round($evaAuto->avg(), 1), 75) !!}</td>
                                        @if (!$comIndicatorCutoff->evaluacion_local)
                                            <td>{!! \Helper::muestra_semaforo(
                                                $evaPreliminar->whereNull()->isNotEmpty() ? null : round($evaPreliminar->avg(), 1),
                                                75,
                                            ) !!}</td>
                                            <td>{!! \Helper::muestra_semaforo($evaFinal->whereNull()->isNotEmpty() ? null : round($evaFinal->avg(), 1), 75) !!}</td>
                                        @else
                                            <td></td>
                                            <td></td>
                                        @endif
                                    @endif
                                @endif

                                @if ($loop->first)
                                    <td rowspan="{{ $rowspan }}">
                                        <a href="{{ route('voyager.indicadores-comges.show', $comIndicator->id) }}"
                                            class="btn btn-sm btn-warning" style="width: 5rem;">
                                            <i class="voyager-eye"></i> Ver</a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
