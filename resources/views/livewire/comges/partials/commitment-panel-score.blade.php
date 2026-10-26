@props(['commitment', 'isFiltering', 'alertGroups'])

<div wire:key="commitment-{{ $commitment->id }}">
    <x-panel-component col="12" panel="primary" :id="$commitment->id" :collapsable="!$isFiltering">
        <x-slot name="title">
            @if (!$isFiltering)
                <i class="glyphicon glyphicon-chevron-right" style="margin-right:6px; font-size:0.8em; opacity:0.6;"></i>
            @endif
            {{ $commitment->clave }}: {{ $commitment->nombre }}
            <span class="badge" style="background:#ffffff; margin-top:2px;">
                {{ $commitment->comIndicators->count() }}
                indicador{{ $commitment->comIndicators->count() !== 1 ? 'es' : '' }}
            </span>
        </x-slot>
        @if (!$isFiltering)
            <x-slot name="static">
                <x-comges.commitment-performance :commitment="$commitment" />
            </x-slot>
        @endif
        <x-slot name="body">
            <table class="table table-bordered table-results commitment-indicators-table">
                <colgroup>
                    <col style="width: 48%;">
                    <col style="width: 14%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                    <col style="width: 8rem;">
                </colgroup>
                <caption>
                    <h5>Indicadores del compromiso:</h5>
                </caption>
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
                    @php
                        $cellAlerts = function (string $anchorKey, string $placement) use ($alertGroups): array {
                            return $alertGroups[$anchorKey . '|' . $placement] ?? [];
                        };
                    @endphp
                    @foreach ($commitment->comIndicators as $comIndicator)
                        @php
                            $cutoffs = $comIndicator->comIndicatorCutoffs;
                            $rowspan = max($cutoffs->count(), 1);
                        @endphp

                        @if ($cutoffs->isEmpty())
                            <tr wire:key="indicator-empty-{{ $comIndicator->id }}">
                                <td class="text-left">
                                    <h4>{{ $comIndicator->clave }}:</h4> {{ $comIndicator->nombre }}
                                    @if (!is_null($comIndicator->ponderacion_indicador))
                                        <div class="small text-muted">
                                            Ponderación:
                                            {{ number_format((float) $comIndicator->ponderacion_indicador, 2, ',', '.') }}
                                            %
                                        </div>
                                    @endif
                                    @include('partials.indicators.ambitos-badges-under-name', [
                                        'ambitos' => $comIndicator->indicatorAmbitos ?? collect(),
                                    ])
                                </td>
                                <td colspan="4"></td>
                                <td>
                                    <a href="{{ route('voyager.indicadores-comges.show', $comIndicator->id) }}"
                                        class="btn btn-sm btn-warning" style="width: 8rem;">
                                        <i class="voyager-eye"></i> Ver</a><br>
                                </td>
                            </tr>
                        @endif

                        @foreach ($cutoffs as $comIndicatorCutoff)
                            @php
                                $cutoffAnchor = 'cutoff:' . $comIndicatorCutoff->id;
                                $apelAnchor = 'indicator-apel:' . $comIndicator->id;
                            @endphp
                            <tr wire:key="indicator-cutoff-{{ $comIndicator->id }}-{{ $comIndicatorCutoff->id }}">
                                @if ($loop->first)
                                    <td rowspan="{{ $rowspan }}" class="text-left">
                                        <h4>{{ $comIndicator->clave }}:</h4>
                                        {{ $comIndicator->nombre }}
                                        @if (!is_null($comIndicator->ponderacion_indicador))
                                            <div class="small text-muted">
                                                Ponderación:
                                                {{ number_format((float) $comIndicator->ponderacion_indicador, 2, ',', '.') }}
                                                %
                                            </div>
                                        @endif
                                        @include('partials.indicators.ambitos-badges-under-name', [
                                            'ambitos' => $comIndicator->indicatorAmbitos ?? collect(),
                                        ])
                                    </td>
                                @endif

                                @if ($comIndicatorCutoff->cutoff->clave === \App\Enums\Corte\CutoffClave::Apelacion)
                                    <td class="resultado-puntajes-destacados commitment-col-componente">
                                        <strong>Resultado</strong>
                                    </td>
                                    <td class="resultado-puntajes-destacados">
                                        <div class="centinela-table-cell-inline centinela-table-cell-inline--score">
                                            <span class="centinela-semaforo-with-alerts">
                                                {!! \Helper::muestra_puntaje($comIndicator->puntajePonderado('AUTOEVALUACION')) !!}
                                                <x-centinela.visual-alert-stack display="icon" variant="corner"
                                                    :alerts="$cellAlerts($apelAnchor, 'evaluation_auto')" />
                                            </span>
                                        </div>
                                    </td>
                                    <td class="resultado-puntajes-destacados">
                                        <div class="centinela-table-cell-inline centinela-table-cell-inline--score">
                                            <span class="centinela-semaforo-with-alerts">
                                                {!! \Helper::muestra_puntaje($comIndicator->puntajePonderado('PRELIMINAR')) !!}
                                                <x-centinela.visual-alert-stack display="icon" variant="corner"
                                                    :alerts="$cellAlerts($apelAnchor, 'evaluation_preliminar')" />
                                            </span>
                                        </div>
                                    </td>
                                    <td class="resultado-puntajes-destacados">{!! \Helper::muestra_puntaje($comIndicator->puntajePonderado('FINAL')) !!}
                                    </td>
                                @else
                                    <td nowrap class="commitment-col-componente">
                                        <div class="centinela-table-cell-inline">
                                            <div class="centinela-table-cell-inline__body">
                                                <strong>{{ $comIndicatorCutoff->cutoff->nombre_corte }}</strong>
                                                @if (!is_null($comIndicatorCutoff->ponderacion_corte))
                                                    <div class="small text-muted">
                                                        Ponderación:
                                                        {{ number_format((float) $comIndicatorCutoff->ponderacion_corte, 2, ',', '.') }}
                                                        %
                                                    </div>
                                                @endif
                                            </div>
                                            <x-centinela.visual-alert-stack display="icon" :alerts="$cellAlerts($cutoffAnchor, 'cutoff_label')" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="centinela-table-cell-inline centinela-table-cell-inline--score">
                                            <span class="centinela-semaforo-with-alerts">
                                                {!! \Helper::muestra_puntaje($comIndicatorCutoff->promedioCumplimiento('AUTOEVALUACION')) !!}
                                                <x-centinela.visual-alert-stack display="icon" variant="corner"
                                                    :alerts="$cellAlerts($cutoffAnchor, 'evaluation_auto')" />
                                            </span>
                                        </div>
                                    </td>
                                    @if (!$comIndicatorCutoff->evaluacion_local)
                                        <td>
                                            <div class="centinela-table-cell-inline centinela-table-cell-inline--score">
                                                <span class="centinela-semaforo-with-alerts">
                                                    {!! \Helper::muestra_puntaje($comIndicatorCutoff->promedioCumplimiento('PRELIMINAR')) !!}
                                                    <x-centinela.visual-alert-stack display="icon" variant="corner"
                                                        :alerts="$cellAlerts($cutoffAnchor, 'evaluation_preliminar')" />
                                                </span>
                                            </div>
                                        </td>
                                        <td>{!! \Helper::muestra_puntaje($comIndicatorCutoff->promedioCumplimiento('FINAL')) !!}</td>
                                    @else
                                        <td></td>
                                        <td></td>
                                    @endif
                                @endif

                                @if ($loop->first)
                                    <td rowspan="{{ $rowspan }}">
                                        <a href="{{ route('voyager.indicadores-comges.show', $comIndicator->id) }}"
                                            class="btn btn-sm btn-warning" style="width: 5rem;">
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
