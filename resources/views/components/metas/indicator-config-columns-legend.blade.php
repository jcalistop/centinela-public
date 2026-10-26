@props([
    'showAgrupaSs' => true,
])

<div class="metas-ficha-legend metas-indicator-config-legend">
    <p><strong>Significado de las columnas</strong></p>
    <ul class="metas-ficha-legend__list">
        <li>
            <strong>Aplica:</strong> el indicador participa en la medición de ese establecimiento para el año
            activo. Con «No», el centro no entra en carga ni en el cálculo de este indicador.
        </li>
        @if ($showAgrupaSs)
            <li>
                <strong>Agrupa SS:</strong> el resultado del establecimiento se calcula con los datos de todos los
                centros del Servicio de Salud con «Aplica», no solo con los del propio establecimiento.
            </li>
        @endif
        <li>
            <strong>En convenio:</strong> el indicador está seleccionado en el convenio del establecimiento
            (incluido en evaluación preliminar, final y etapas asociadas).
        </li>
    </ul>
</div>
