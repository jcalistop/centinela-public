@props(['commitment'])

@php
    $pAuto = $commitment->puntajePonderado('AUTOEVALUACION');
    $pPreliminar = $commitment->puntajePonderado('PRELIMINAR');
    $pFinal = $commitment->puntajePonderado('FINAL');

    $puntajeMaximo = 4.0;
    $pctAuto = is_null($pAuto) ? null : round(($pAuto / 4) * 100, 0);
    $pctPreliminar = is_null($pPreliminar) ? null : round(($pPreliminar / 4) * 100, 0);
    $pctFinal = is_null($pFinal) ? null : round(($pFinal / 4) * 100, 0);

    $fmtPtos = function (?float $v): string {
        if (is_null($v)) {
            return 'S/I';
        }

        $ptos = round($v, 2);

        return "{$ptos} ptos.";
    };
@endphp

<table class="table table-bordered table-results commitment-performance-table" style="margin-bottom: 1rem;">
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
            <th>&nbsp;</th>
            <th>Máximo</th>
            <th>Autoevaluación</th>
            <th>Evaluación preliminar</th>
            <th>Evaluación final</th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-left">
                Desempeño global del compromiso en puntaje
            </td>
            <td>
                <span class="text-muted">{{ $fmtPtos($puntajeMaximo) }}</span>
            </td>
            <td>
                {!! \Helper::muestra_puntaje($pAuto) !!}
            </td>
            <td>
                {!! \Helper::muestra_puntaje($pPreliminar) !!}
            </td>
            <td>
                {!! \Helper::muestra_puntaje($pFinal) !!}
            </td>
            <td rowspan="2">
                <button type="button" class="btn btn-sm btn-warning" style="width: 5rem;"
                    onclick="if (window.jQuery) { jQuery(this).closest('.panel').find('.panel-collapse').collapse('toggle'); }">
                    <i class="voyager-double-down"></i> Ver
                </button>
            </td>
        </tr>
        <tr>
            <td class="text-left">
                Desempeño global del compromiso en porcentaje
            </td>
            <td>
                <span class="text-muted">100%</span>
            </td>
            <td>
                {!! \Helper::muestra_semaforo($pctAuto, 75) !!}
            </td>
            <td>
                {!! \Helper::muestra_semaforo($pctPreliminar, 75) !!}
            </td>
            <td>
                {!! \Helper::muestra_semaforo($pctFinal, 75) !!}
            </td>
        </tr>
    </tbody>
</table>
