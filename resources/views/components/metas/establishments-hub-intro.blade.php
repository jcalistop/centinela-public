@props([
    'usesInternalUnitMeasurement' => false,
    'instrumentNombre' => '',
    'year' => '',
])

<div class="metas-establishments-intro text-muted">
    <p>
        Seleccione los establecimientos que participarán en la medición
        @if ($instrumentNombre !== '')
            de <strong>{{ $instrumentNombre }}</strong>
        @else
            de este instrumento
        @endif
        @if ($year !== '')
            para el año <strong>{{ $year }}</strong>
        @else
            para el año activo
        @endif
        . Por defecto, <strong>ningún establecimiento está incluido</strong>.
        Use los botones de cada fila para incluir o excluir participantes y abrir su configuración.
    </p>
    <p><strong>Acciones por fila</strong></p>
    <ul class="metas-ficha-legend__list">
        <li>
            <strong>Incluir:</strong> agrega el establecimiento a la medición del año en curso.
        </li>
        <li>
            <strong>Excluir:</strong> lo quita de la medición. Si ya tiene resultados cargados, se pedirá confirmación.
        </li>
        <li>
            <strong>Indicadores:</strong> abre la configuración de qué indicadores aplican, si agrupan al Servicio de
            Salud,
            cuáles están en convenio y sus ponderaciones para ese establecimiento.
        </li>
        @if ($usesInternalUnitMeasurement)
            <li>
                <strong>Unidades:</strong> gestiona las unidades internas de medición del establecimiento cuando el
                instrumento
                las utiliza en lugar de adosados.
            </li>
        @else
            <li>
                <strong>Adosados:</strong> gestiona centros cuyos datos de numerador y denominador se suman al cálculo
                del
                establecimiento madre.
            </li>
        @endif
        <li>
            <strong>Responsables:</strong> designa usuarios referentes responsables del establecimiento (convenio,
            ponderaciones, carga acotada y apelaciones).
        </li>
    </ul>
</div>
