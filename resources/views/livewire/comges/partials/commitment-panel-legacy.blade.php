@props(['commitment'])

<div wire:key="commitment-{{ $commitment->id }}">
    <x-panel-component col="12" panel="primary">
        <x-slot name="title">
            {{ $commitment->clave }}: {{ $commitment->nombre }}
        </x-slot>
        <x-slot name="body">
            <table class="table table-bordered table-results">
                <caption>
                    <h5>Indicadores del compromiso:</h5>
                </caption>
                <thead>
                    <tr>
                        <th>Indicador</th>
                        <th style="width: 40%">Nombre</th>
                        <th>Ponderación</th>
                        <th>Corte</th>
                        <th>Autoevaluación</th>
                        <th>Evaluación preliminar</th>
                        <th>Evaluación final</th>
                        <th>Promedio país</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($commitment->comIndicators as $comIndicator)
                        @php
                            $cutoffs = $comIndicator->comIndicatorCutoffs;
                            $rowspan = max($cutoffs->count(), 1);
                        @endphp

                        @if ($cutoffs->isEmpty())
                            <tr wire:key="legacy-indicator-empty-{{ $comIndicator->id }}">
                                <td>{{ $comIndicator->clave }}</td>
                                <td class="text-left">
                                    {{ $comIndicator->nombre }}
                                    @include('partials.indicators.ambitos-badges-under-name', [
                                        'ambitos' => $comIndicator->indicatorAmbitos ?? collect(),
                                    ])
                                </td>
                                <td>{{ $comIndicator->ponderacion_indicador }}</td>
                                <td colspan="4"></td>
                                <td></td>
                                <td>
                                    <a href="{{ route('voyager.indicadores-comges.show', $comIndicator->id) }}"
                                        class="btn btn-sm btn-warning" style="width: 8rem;">
                                        <i class="voyager-eye"></i> Ver</a><br>
                                </td>
                            </tr>
                        @endif

                        @foreach ($cutoffs as $comIndicatorCutoff)
                            @php
                                $evaAuto = collect();
                                $evaPreliminar = collect();
                                $evaFinal = collect();
                                $actionsPonderacion = 0.0;
                                foreach ($comIndicatorCutoff->comActions as $comAction) {
                                    $evaAuto[] = $comAction->comEvaluations->firstWhere(
                                        'tipo_evaluacion',
                                        \App\Enums\Comges\EvaluacionTipo::Autoevaluacion,
                                    )?->cumplimiento;
                                    $evaPreliminar[] = $comAction->comEvaluations->firstWhere(
                                        'tipo_evaluacion',
                                        \App\Enums\Comges\EvaluacionTipo::Preliminar,
                                    )?->cumplimiento;
                                    $evaFinal[] = $comAction->comEvaluations->firstWhere(
                                        'tipo_evaluacion',
                                        \App\Enums\Comges\EvaluacionTipo::Final,
                                    )?->cumplimiento;
                                    $actionsPonderacion += (float) ($comAction->ponderacion_accion ?? 0);
                                }
                                $actionsPonderacionRounded = round($actionsPonderacion, 2);
                                $componentePonderacionAlert =
                                    in_array(Auth::user()->role->name, ['parametrizador', 'admin'], true) &&
                                    $actionsPonderacionRounded != 100.0;
                            @endphp
                            <tr
                                wire:key="legacy-indicator-cutoff-{{ $comIndicator->id }}-{{ $comIndicatorCutoff->id }}">
                                @if ($loop->first)
                                    <td rowspan="{{ $rowspan }}">{{ $comIndicator->clave }}</td>
                                    <td rowspan="{{ $rowspan }}" class="text-left">
                                        {{ $comIndicator->nombre }}
                                        @include('partials.indicators.ambitos-badges-under-name', [
                                            'ambitos' => $comIndicator->indicatorAmbitos ?? collect(),
                                        ])
                                    </td>
                                    <td rowspan="{{ $rowspan }}">{{ $comIndicator->ponderacion_indicador }}</td>
                                @endif
                                <td nowrap>
                                    @if ($componentePonderacionAlert)
                                        <div class="componente-corte-alerta"
                                            title="La suma de ponderaciones de las acciones para este corte debe ser 100%.">
                                            <span class="glyphicon glyphicon-exclamation-sign text-warning"
                                                aria-hidden="true"></span><strong>{{ $comIndicatorCutoff->cutoff?->clave ?? $comIndicatorCutoff->Cutoff?->clave }}</strong>
                                            <div class="small text-muted">Ponderación acciones:
                                                {{ $actionsPonderacionRounded }}% (debe ser 100%)</div>
                                        </div>
                                    @else
                                        {{ $comIndicatorCutoff->cutoff?->clave ?? $comIndicatorCutoff->Cutoff?->clave }}
                                    @endif
                                </td>
                                <td>{!! \Helper::muestra_semaforo($evaAuto->whereNull()->isNotEmpty() ? null : round($evaAuto->avg(), 1), 75) !!}</td>
                                <td>{!! \Helper::muestra_semaforo(
                                    $evaPreliminar->whereNull()->isNotEmpty() ? null : round($evaPreliminar->avg(), 1),
                                    75,
                                ) !!}</td>
                                <td>{!! \Helper::muestra_semaforo($evaFinal->whereNull()->isNotEmpty() ? null : round($evaFinal->avg(), 1), 75) !!}</td>
                                <td>{{ $comIndicatorCutoff->promedio_pais ? $comIndicatorCutoff->promedio_pais . ' %' : '' }}
                                </td>
                                @if ($loop->first)
                                    <td rowspan="{{ $rowspan }}">
                                        <a href="{{ route('voyager.indicadores-comges.show', $comIndicator->id) }}"
                                            class="btn btn-sm btn-warning" style="width: 8rem;">
                                            <i class="voyager-eye"></i> Ver</a><br>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </x-slot>
        <x-slot name="footer">
            @can('add', app('App\Models\Comindicator'))
                <a href="{{ route('voyager.indicadores-comges.create', ['com_commitment_id' => $commitment->id]) }}"
                    class="btn btn-sm btn-success"><i class="glyphicon glyphicon-plus-sign"></i>
                    Agregar indicador</a>
            @endcan

            @can('edit', app('App\Models\ComCommitment'))
                <a href="{{ route('voyager.compromisos.edit', $commitment->id) }}" class="btn btn-primary">
                    <i class="glyphicon glyphicon-pencil"></i> Editar Compromiso
                </a>
            @endcan
            @if ($commitment->comIndicators->count() == 0)
                @can('delete', app('App\Models\ComCommitment'))
                    <a href="javascript:;" title="{{ __('voyager::generic.delete') }}" class="btn btn-danger delete"
                        data-id="{{ $commitment->id }}" id="delete-{{ $commitment->id }}">
                        <i class="voyager-trash"></i>&nbsp;{{ __('voyager::generic.delete') }}
                    </a>
                @endcan
            @endif
        </x-slot>
    </x-panel-component>
</div>
