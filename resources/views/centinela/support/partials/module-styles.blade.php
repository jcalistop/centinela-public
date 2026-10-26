{{-- Estilos con raíz .support-module (después del stack Voyager). SDD-050 --}}
<style>
    .voyager .support-module .support-badge-unread {
        background: #2ecc71;
        color: #fff;
        font-size: 0.72rem;
        padding: 0.15rem 0.35rem;
        border-radius: 3px;
    }

    .voyager .support-module .support-row-unread td {
        font-weight: 600;
    }

    .voyager .support-module .support-thread {
        border-left: 3px solid #ddd;
        padding-left: 1rem;
        margin-bottom: 1.25rem;
    }

    .voyager .support-module .support-thread-meta {
        font-size: 0.85rem;
        color: #666;
    }

    .voyager .support-module .panel-bordered .panel-body form {
        padding-left: 50px;
        padding-right: 50px;
    }

    .voyager .support-module .panel-bordered .panel-body table form,
    .voyager .support-compass.cen-compass-ui .cen-dashboard-block__body table form {
        padding-left: 0;
        padding-right: 0;
    }

    /* Mayor separación horizontal entre dos columnas (gutter BS3 + 10px por lado). */
    .voyager .support-module .support-form-row-2col>.col-md-6:first-of-type {
        padding-right: 25px;
    }

    .voyager .support-module .support-form-row-2col>.col-md-6:last-of-type {
        padding-left: 25px;
    }

    .voyager .support-module .support-ticket-summary .support-ticket-subject {
        font-weight: 600;
        color: #333;
        line-height: 1.3;
    }

    .voyager .support-module .support-ticket-description {
        word-break: break-word;
    }

    .voyager .support-module .support-detail-dl dt {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #777;
        margin-top: 0.85rem;
        margin-bottom: 0.2rem;
        font-weight: 600;
    }

    .voyager .support-module .support-detail-dl dt:first-child {
        margin-top: 0;
    }

    .voyager .support-module .support-detail-dl dd {
        margin-left: 0;
        margin-bottom: 0.15rem;
        font-size: 0.95rem;
    }

    .voyager .support-module .support-thread-empty {
        font-style: italic;
    }
</style>
