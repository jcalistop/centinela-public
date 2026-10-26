{{-- Puente Soporte ↔ kit Compass Centinela (cen-compass-ui.css). SDD-101 / SDD-052 --}}
<style>
    .voyager .support-compass.cen-compass-ui {
        --support-color-open: var(--cen-accent, #62a8ea);
        --support-color-tecnico: var(--cen-accent, #62a8ea);
        --support-field-border: #aaaaaa;
    }

    .voyager .support-compass.cen-compass-ui .support-form-section-title {
        color: var(--cen-text, #2a3f54);
        border-bottom-color: var(--cen-border, #e8edf2);
    }

    .voyager .support-compass.cen-compass-ui .support-detail-dl dt {
        color: var(--cen-meta, #8a9bb0);
    }

    .voyager .support-compass.cen-compass-ui .support-ticket-subject {
        color: var(--cen-text, #2a3f54);
    }

    .voyager .support-compass.cen-compass-ui .support-hilo-scroll-outer {
        border-color: var(--cen-border, #e8edf2);
        background: #fafbfc;
    }

    .voyager .support-compass.cen-compass-ui .support-thread-message-body {
        border-left-color: var(--cen-accent, #62a8ea);
    }

    .voyager .support-compass.cen-compass-ui .btn-primary {
        background-color: var(--cen-accent, #62a8ea);
        border-color: var(--cen-accent, #62a8ea);
    }

    .voyager .support-compass.cen-compass-ui .btn-primary:hover,
    .voyager .support-compass.cen-compass-ui .btn-primary:focus {
        background-color: #4f97d9;
        border-color: #4f97d9;
    }

    /* Navegación y acciones secundarias: success (evitar btn-default gris). */
    .voyager .support-compass.cen-compass-ui .btn-success {
        background-color: #27ae60;
        border-color: #27ae60;
    }

    .voyager .support-compass.cen-compass-ui .btn-success:hover,
    .voyager .support-compass.cen-compass-ui .btn-success:focus {
        background-color: #219a52;
        border-color: #219a52;
    }

    .voyager .support-compass.cen-compass-ui .cen-dashboard-block+.cen-dashboard-block,
    .voyager .support-compass.cen-compass-ui .cen-dashboard-block+.row {
        margin-top: 18px;
    }

    .voyager .support-compass.cen-compass-ui .support-status-assign-row .cen-dashboard-block {
        height: 100%;
    }

    /* Formularios: más aire lateral que el padding base Compass (18px). */
    .voyager .support-compass.cen-compass-ui .cen-dashboard-block__body {
        padding: 22px 40px 26px;
    }

    .voyager .support-compass.cen-compass-ui .cen-dashboard-block__body .form-group {
        margin-bottom: 1.25rem;
    }

    .voyager .support-compass.cen-compass-ui .cen-dashboard-block__body .control-label {
        font-weight: 600;
        color: var(--cen-text, #2a3f54);
    }

    .voyager .support-compass.cen-compass-ui .form-control {
        border-color: var(--support-field-border, #aaaaaa);
    }

    .voyager .support-compass.cen-compass-ui .form-control:focus {
        border-color: var(--cen-accent, #62a8ea);
    }

    /* input file: Bootstrap fija height en .form-control y el botón nativo queda sin aire inferior. */
    .voyager .support-compass.cen-compass-ui input[type="file"].form-control {
        height: auto;
        min-height: 38px;
        padding: 7px 12px;
        line-height: 1.42857143;
    }

    .voyager .support-compass.cen-compass-ui input[type="file"].form-control::file-selector-button {
        margin-right: 10px;
        padding: 4px 10px;
        vertical-align: middle;
    }

    @media (max-width: 767px) {
        .voyager .support-compass.cen-compass-ui .cen-dashboard-block__body {
            padding-left: 22px;
            padding-right: 22px;
        }
    }

    /* Select2: mismo borde que .form-control (Compass / Bootstrap 3). */
    .voyager .support-compass.cen-compass-ui .select2-container {
        width: 100% !important;
    }

    .voyager .support-compass.cen-compass-ui .select2-container--default .select2-selection--single,
    .voyager .support-compass.cen-compass-ui .select2-container--default .select2-selection--multiple {
        border: 1px solid var(--support-field-border, #aaaaaa) !important;
        border-radius: 4px;
        min-height: 38px;
        background-color: #fff;
    }

    .voyager .support-compass.cen-compass-ui .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
        padding-left: 12px;
        color: var(--cen-text, #2a3f54);
    }

    .voyager .support-compass.cen-compass-ui .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }

    .voyager .support-compass.cen-compass-ui .select2-container--default.select2-container--focus .select2-selection--single,
    .voyager .support-compass.cen-compass-ui .select2-container--default.select2-container--open .select2-selection--single {
        border-color: var(--cen-accent, #62a8ea) !important;
        outline: 0;
        box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 6px rgba(98, 168, 234, 0.35);
    }

    .voyager .support-compass.cen-compass-ui .select2-dropdown {
        border-color: var(--support-field-border, #aaaaaa);
    }

    .select2-container--open .select2-dropdown {
        z-index: 10050;
    }

    .voyager .support-compass.cen-compass-ui .requester-user-search-muted.select2-container--default .select2-selection--single {
        opacity: .45;
        pointer-events: none;
        cursor: not-allowed;
    }
</style>
