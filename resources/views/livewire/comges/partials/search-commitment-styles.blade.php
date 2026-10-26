<style>
    .panel-heading [data-toggle="collapse"] .glyphicon-chevron-right {
        transition: transform 0.2s ease;
    }

    .panel:has(.panel-collapse.in) .panel-heading [data-toggle="collapse"] .glyphicon-chevron-right {
        transform: rotate(90deg);
    }

    .table-results .semaforo {
        width: 4rem;
    }

    .commitment-performance-table,
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

    .commitment-indicators-table td.commitment-col-componente .centinela-table-cell-inline {
        justify-content: center;
    }

    .table-results .componente-corte-alerta {
        border-left: 4px solid #f0ad4e;
        padding: 0.35rem 0.35rem 0.35rem 0.5rem;
        margin: -0.25rem -0.5rem;
        background-color: #fcf8f3;
    }

    .table-results .componente-corte-alerta .glyphicon {
        margin-right: 0.35rem;
    }

    .comges-commitment-filters {
        margin-top: 1.25rem;
        margin-bottom: 1.25rem;
    }

    .comges-commitment-filters__row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-left: -8px;
        margin-right: -8px;
    }

    .comges-commitment-filters__field {
        padding-left: 8px;
        padding-right: 8px;
        margin-bottom: 0;
    }

    .comges-commitment-filters__field--search {
        flex: 1 1 58%;
        min-width: 240px;
    }

    .comges-commitment-filters__field--ambito {
        flex: 0 1 280px;
        min-width: 200px;
    }

    .comges-commitment-filters__label {
        display: block;
        font-size: 0.78em;
        font-weight: 600;
        color: #777;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 6px;
    }

    @media (max-width: 767px) {

        .comges-commitment-filters__field--search,
        .comges-commitment-filters__field--ambito {
            flex: 1 1 100%;
            margin-bottom: 12px;
        }

        .comges-commitment-filters__field--ambito:last-child {
            margin-bottom: 0;
        }
    }
</style>
