{{-- SDD-179: vista impresión limpia Metas · SDD-180: captura PNG --}}
<style>
    .print-only {
        display: none !important;
    }

    /* SDD-180: durante captura, ocultar chrome de acción y mostrar cabeceras print-only */
    .capture-only {
        display: none !important;
    }

    body.metas-capturing .no-print,
    body.metas-capturing .no-capture {
        display: none !important;
    }

    body.metas-capturing .print-only {
        display: block !important;
    }

    body.metas-capturing td.capture-only,
    body.metas-capturing th.capture-only {
        display: table-cell !important;
    }

    body.metas-capturing .metas-capture-root {
        background: #fff;
        padding: 8px;
    }

    @media print {
        @page {
            size: landscape;
            margin: 12mm;
        }

        .print-only {
            display: block !important;
        }

        .no-print,
        .side-menu,
        .navbar,
        nav.navbar,
        #voyager-loader,
        .hamburger,
        .app-footer,
        .fadetoturquoise,
        #toast-container,
        .pace,
        .pace-progress,
        .metas-nav-tabs,
        .nav-tabs,
        .tab-pane:not(.active),
        .modal,
        .modal-backdrop,
        .metas-etapa-badge,
        .metas-hub-compass-block>.cen-dashboard-block__head,
        .metas-tab-spotlight,
        .metas-ficha-tab-intro {
            display: none !important;
        }

        .app-container,
        .content-container,
        .side-body,
        .container-fluid,
        .page-content {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        body {
            background: #fff !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .metas-print-header {
            margin: 0 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #ccc;
        }

        .metas-print-header h2 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
        }

        .metas-print-header p {
            margin: 0;
            font-size: 12px;
            color: #333;
        }

        .metas-instrument-table,
        .metas-instrument-table th,
        .metas-instrument-table td {
            font-size: 10px !important;
        }

        /*
         * Etiquetas Bootstrap (.label-success/danger/…): el fondo de color a menudo
         * no se imprime y el texto forzado a blanco queda ilegible (caja vacía).
         * En print: mismo look que label-default — fondo claro, texto oscuro, borde.
         */
        .metas-instrument-table .label,
        .metas-instrument-ui .label,
        .metas-capture-root .label {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color: #333 !important;
            background-color: #fff !important;
            background-image: none !important;
            border: 1px solid #555 !important;
            text-shadow: none !important;
        }

        a[href]::after {
            content: none !important;
        }
    }
</style>
