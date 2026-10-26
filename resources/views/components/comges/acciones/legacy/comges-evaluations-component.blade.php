@php
    use App\Enums\Comges\EvaluacionTipo;

    $etapas = [
        [
            'titulo' => 'Autoevaluación (Referente)',
            'tipo_evaluacion' => 'AUTOEVALUACION',
        ],
        [
            'titulo' => 'Evaluación Preliminar (Minsal)',
            'tipo_evaluacion' => 'PRELIMINAR',
        ],
        [
            'titulo' => 'Evaluación Final (Minsal)',
            'tipo_evaluacion' => 'FINAL',
        ],
    ];

    // Filtrar las evaluaciones por tipo de evaluación
    $evaluacionesFiltradas = [];
    foreach ($etapas as $etapa) {
        $evaluacionesFiltradas[$etapa['tipo_evaluacion']] = $evaluations
            ->where('tipo_evaluacion', EvaluacionTipo::from($etapa['tipo_evaluacion']))
            ->first();
    }
@endphp
<div>
    <table class="table table-results" style="margin: 0;">
        <thead>
            <tr>
                <th width="30%">Evaluación de la accióndfsdf</th>
                <th width="13%">Numerador (A)</th>
                <th width="13%">Denominador (B)</th>
                <th width="13%">Resultado (A/B) x 100</th>
                <th width="13%">Meta</th>
                <th width="13%">Cumplimiento</th>
                @if (
                    (Auth::user()->role->name == 'referente') |
                        (Auth::user()->role->name == 'control-gestion') |
                        (Auth::user()->role->name == 'admin'))
                    <th width="5%"></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($etapas as $etapa)
                @php
                    $eva = $evaluacionesFiltradas[$etapa['tipo_evaluacion']];
                @endphp
                <tr>
                    <td rowspan="2">{{ $etapa['titulo'] }}</td>
                    <td>{{ $eva->numerador ?? '' }}</td>
                    <td>{{ $eva->denominador ?? '' }}</td>
                    <td>{{ $eva->resultado ?? '' }}</td>
                    <td>{{ $eva->meta ?? '' }}</td>
                    <td>{{ isset($eva->cumplimiento) ? $eva->cumplimiento . '%' : '' }}</td>
                    <td style="padding: 0 0 0 0;">
                        @can('current-add-edit', [$action->id, $etapa['tipo_evaluacion']])
                            @php
                                $hasSensitivityTable = $action->comSensitivityTables->count() > 0;
                                $hasDependencyTable =
                                    $action->comSensitivityTables
                                        ->filter(function ($table) {
                                            return $table->comSensitivityDependencies
                                                ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
                                                ->count() > 0;
                                        })
                                        ->count() > 0;
                            @endphp

                            @if ($hasSensitivityTable && $hasDependencyTable)
                                <a href="{{ !is_null($eva)
                                    ? route('voyager.evaluaciones-comges.edit', $eva->id)
                                    : route('voyager.evaluaciones-comges.create', [
                                        'com_action_id' => $action->id,
                                        'tipo_evaluacion' => $etapa['tipo_evaluacion'],
                                    ]) }}"
                                    class="btn btn-sm btn-success">
                                    <i class="glyphicon glyphicon-pencil"></i>
                                </a>
                            @else
                                <button class="btn btn-sm btn-default" disabled
                                    title="{{ !$hasSensitivityTable ? 'No hay tabla de sensibilidad creada para esta acción' : 'Control de Gestión debe seleccionar una tabla de sensibilidad para su Servicio de Salud' }}">
                                    <i class="glyphicon glyphicon-pencil"></i>
                                </button>
                            @endif
                        @endcan
                    </td>
                </tr>
                <tr>
                    @isset($eva->observaciones)
                        <td colspan="6" class="text-left">
                            Observaciones: {!! $eva->observaciones !!}
                        </td>
                    @endisset
                </tr>
            @endforeach

        </tbody>
    </table>
</div>
