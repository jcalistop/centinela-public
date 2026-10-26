@props(['showAdosadoLegend' => true])

<div class="metas-ficha-legend metas-establishments-legend">
    <p><strong>Significado de las columnas</strong></p>
    <ul class="metas-ficha-legend__list">
        <li>
            <strong>Aplica:</strong> el indicador participa en la medición de ese establecimiento para el año
            activo. Con «No», el centro no entra en carga ni en el cálculo de este indicador.
        </li>
        <li>
            <strong>En convenio:</strong> el indicador está seleccionado en el convenio del establecimiento
            (incluido en evaluación preliminar, final y etapas asociadas).
        </li>
        <li>
            <strong>Agrupa SS:</strong> el resultado del establecimiento se calcula con los datos de todos los
            centros del Servicio de Salud con «Aplica», no solo con los del propio establecimiento.
        </li>
    </ul>
    @if ($showAdosadoLegend)
        <p>
            <strong>Adosado</strong>
            <span class="label label-info metas-center-adosado-label">nombre adosado</span>:
            establecimiento cuyos datos de numerador y denominador se incorporan al
            cálculo del centro madre bajo el cual aparece listado.
        </p>
    @endif
    <p class="text-muted small metas-ficha-legend__footnote">
        Los establecimientos participantes son configurados por Control de Gestión.
    </p>
</div>
