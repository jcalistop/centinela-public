<style>
    .metas-instrument-action {
        top: 0 !important;
        left: 0 !important;
    }

    .metas-etapa-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: nowrap;
    }

    /* Voyager fija height:100px + line-height:43px para una sola línea: un título
       largo desborda y la segunda línea queda tapada por .page-content. Se libera
       la altura y se permite el quiebre dentro del ancho disponible, sin empujar
       el badge a otra fila. */
    .metas-etapa-header .page-title {
        flex: 1 1 0%;
        min-width: 0;
        height: auto !important;
        min-height: 100px;
        line-height: 1.4 !important;
        margin-right: 0;
        padding-bottom: 6px;
        overflow-wrap: anywhere;
    }

    .metas-etapa-badge {
        position: static !important;
        flex: 0 0 auto;
        margin-top: 28px;
        transform: none !important;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.35;
        padding: 0.4em 0.7em;
        white-space: nowrap;
    }

    /* Vista de resultados con contexto de unidad: el título se ajusta a su
       contenido (sin los 100px fijos de Voyager) y la unidad queda en su propia
       línea con separación. */
    .metas-etapa-header__main {
        flex: 1 1 0%;
        min-width: 0;
    }

    .metas-etapa-header--with-unit .page-title {
        min-height: 0;
        padding-bottom: 0;
    }

    .metas-etapa-header--with-unit .metas-results-unit-header-context {
        margin-top: 8px;
        padding-left: 75px;
        font-size: 15px;
        font-weight: 600;
        line-height: 1.35;
        color: #555;
    }

    .metas-etapa-header .metas-results-unit-header-context code {
        font-size: 0.9em;
        line-height: inherit;
        vertical-align: baseline;
        white-space: nowrap;
    }
</style>
