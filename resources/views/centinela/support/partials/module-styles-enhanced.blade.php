{{-- Estilos mejorados con scope `.support-module` (despues del stack Voyager). SDD-052 --}}
{{-- Se incluye DESPUES de module-styles.blade.php para override progresivo. --}}
<style>
    /* ================================================================
       module-styles-enhanced.blade.php
       Mejoras UI/UX modulo Soporte. SDD-052.
       Se incluye DESPUES de module-styles.blade.php para override.
       ================================================================ */

    /* --- Variables del modulo --- */
    .voyager .support-module .btn>i[class^="voyager-"],
    .voyager .support-module .btn>i[class*=" voyager-"] {
        margin-right: 6px;
    }

    .voyager .support-module .support-count-pill>i[class^="voyager-"] {
        margin-right: 5px;
        opacity: 0.95;
    }

    .voyager .support-module .support-page-actions>.btn+.btn {
        margin-left: 8px;
    }

    .voyager .support-module {
        --support-color-open: #3498db;
        --support-color-in-progress: #f39c12;
        --support-color-resolved: #2ecc71;
        --support-color-closed: #566573;
        --support-color-tecnico: #2980b9;
        --support-color-gestion: #e67e22;
        --support-avatar-size: 36px;
        --support-thread-max-width: 720px;
        --support-hilo-scroll-height: min(52vh, 520px);
    }

    /* --- Toolbar / filtros --- */
    .voyager .support-module .support-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
    }

    .voyager .support-module .support-toolbar-form {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1 1 280px;
    }

    .voyager .support-module .support-toolbar .form-control {
        max-width: 220px;
        display: inline-block;
    }

    .voyager .support-module .support-toolbar .support-toolbar-search {
        flex: 1 1 auto;
        min-width: 140px;
        max-width: 320px;
        width: auto;
    }

    .voyager .support-module .support-toolbar-form>.btn {
        flex-shrink: 0;
    }

    /* --- Pills de conteo por estado --- */
    .voyager .support-module .support-count-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 15px;
    }

    .voyager .support-module .support-count-pill {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.15s, box-shadow 0.15s;
        text-decoration: none;
        color: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.12);
    }

    .voyager .support-module .support-count-pill:hover {
        opacity: 0.85;
        text-decoration: none;
        color: #fff !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    .voyager .support-module .support-count-pill--open {
        background: var(--support-color-open);
    }

    .voyager .support-module .support-count-pill--in_progress {
        background: var(--support-color-in-progress);
    }

    .voyager .support-module .support-count-pill--resolved {
        background: var(--support-color-resolved);
    }

    .voyager .support-module .support-count-pill--all {
        background: var(--support-color-closed);
    }

    .voyager .support-module .support-count-pill--closed {
        background: #e0e0e0;
        color: #555 !important;
        border-color: #ccc;
    }

    .voyager .support-module .support-count-pill--closed:hover {
        color: #555 !important;
    }

    /* --- Row styling por estado --- */
    .voyager .support-module .support-row--open td {
        background-color: #ebf5fb;
    }

    .voyager .support-module .support-row--in_progress td {
        background-color: #fef5e7;
    }

    .voyager .support-module .support-row--resolved td {
        background-color: #eafaf1;
    }

    .voyager .support-module .support-row--closed td {
        color: #999;
    }

    /*
     * Estado (tabla / detalle): mismo lenguaje visual que las burbujas de la bandeja
     * (.support-count-pill--*): tipografia sin mayusculas forzadas, padding y radio igual.
     */
    .voyager .support-module .support-status-badge {
        display: inline-block !important;
        padding: 4px 14px !important;
        border-radius: 20px !important;
        font-size: 0.82rem !important;
        font-weight: 600 !important;
        text-transform: none !important;
        letter-spacing: normal !important;
        line-height: 1.25 !important;
        vertical-align: middle !important;
        border: 1px solid rgba(0, 0, 0, 0.12) !important;
        color: #fff !important;
    }

    .voyager .support-module .support-status-badge--open {
        background: var(--support-color-open) !important;
    }

    .voyager .support-module .support-status-badge--in_progress {
        background: var(--support-color-in-progress) !important;
    }

    .voyager .support-module .support-status-badge--resolved {
        background: var(--support-color-resolved) !important;
    }

    .voyager .support-module .support-status-badge--closed {
        background: #e0e0e0 !important;
        color: #555 !important;
        border-color: #ccc !important;
    }

    /* --- Badge de area --- */
    .voyager .support-module .support-area-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #fff;
        white-space: nowrap;
    }

    .voyager .support-module .support-area-badge--tecnico {
        background: var(--support-color-tecnico);
    }

    .voyager .support-module .support-area-badge--gestion {
        background: var(--support-color-gestion);
    }

    /* --- Timestamp relativo --- */
    .voyager .support-module .support-relative-time {
        font-size: 0.82rem;
        color: #888;
        white-space: nowrap;
    }

    /* --- Empty state mejorado --- */
    .voyager .support-module .support-empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #999;
    }

    .voyager .support-module .support-empty-state .support-empty-icon {
        font-size: 3.5rem;
        display: block;
        margin-bottom: 14px;
        color: #ccc;
    }

    .voyager .support-module .support-empty-state p {
        font-size: 1.05rem;
        margin-bottom: 18px;
        color: #888;
    }

    /* --- Avatares de iniciales --- */
    .voyager .support-module .support-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--support-avatar-size);
        height: var(--support-avatar-size);
        border-radius: 50%;
        color: #fff;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
        text-transform: uppercase;
        user-select: none;
    }

    .voyager .support-module .support-avatar--sm {
        width: 28px;
        height: 28px;
        font-size: 0.7rem;
    }

    /* --- Hilo tipo chat --- */
    .voyager .support-module .support-thread-wrapper {
        max-width: var(--support-thread-max-width);
    }

    /* --- Hilo: dos columnas + area de mensajes con alto fijo y scroll --- */
    .voyager .support-module .support-hilo-scroll-outer {
        height: var(--support-hilo-scroll-height);
        min-height: 240px;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        background: #fafafa;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .voyager .support-module .support-hilo-thread-scroll {
        height: 100%;
        overflow-x: hidden;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 12px 14px 10px;
    }

    .voyager .support-module .support-hilo-thread-scroll .support-thread-inner.support-thread-wrapper {
        max-width: none;
    }

    .voyager .support-module .support-hilo-thread-col {
        margin-bottom: 18px;
    }

    @media (min-width: 992px) {
        .voyager .support-module .support-hilo-layout .support-hilo-thread-col {
            margin-bottom: 0;
        }

        .voyager .support-module .support-hilo-reply-col {
            padding-left: 22px;
            border-left: 1px solid #e8e8e8;
            min-height: 120px;
        }
    }

    @media (max-width: 991px) {
        .voyager .support-module .support-hilo-reply-col {
            padding-top: 18px;
            margin-top: 4px;
            border-top: 1px solid #e8e8e8;
        }
    }

    .voyager .support-module .support-scroll-bottom--in-thread {
        margin-top: 10px;
        margin-bottom: 0;
    }

    .voyager .support-module .support-thread-message {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
    }

    .voyager .support-module .support-thread-message-body {
        flex: 1;
        background: #f0f7ff;
        border-left: 3px solid var(--support-color-open);
        border-radius: 4px;
        padding: 10px 14px;
    }

    .voyager .support-module .support-thread-message--self .support-thread-message-body {
        background: #f0fff4;
        border-left-color: var(--support-color-resolved);
    }

    .voyager .support-module .support-thread-event {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 14px;
        color: #888;
        font-size: 0.88rem;
    }

    .voyager .support-module .support-thread-event-icon {
        font-size: 1.1rem;
        flex-shrink: 0;
        margin-top: 3px;
        color: #aaa;
    }

    .voyager .support-module .support-thread-event-body {
        flex: 1;
    }

    .voyager .support-module .support-thread-event-divider {
        border: 0;
        border-top: 1px solid #eee;
        margin: 6px 0;
    }

    .voyager .support-module .support-thread-header {
        margin-bottom: 4px;
    }

    .voyager .support-module .support-thread-author {
        font-weight: 600;
        color: #444;
        font-size: 0.88rem;
    }

    .voyager .support-module .support-thread-time {
        font-size: 0.78rem;
        color: #aaa;
        margin-left: 6px;
    }

    /* --- Boton scroll al ultimo mensaje --- */
    .voyager .support-module .support-scroll-bottom {
        position: sticky;
        bottom: 10px;
        z-index: 10;
        text-align: center;
        margin-top: -30px;
        pointer-events: none;
    }

    .voyager .support-module .support-scroll-bottom .btn {
        pointer-events: auto;
        border-radius: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        transition: opacity 0.2s;
    }

    .voyager .support-module .support-scroll-bottom .btn:hover {
        opacity: 0.9;
    }

    /* --- Contador de caracteres --- */
    .voyager .support-module .support-char-counter {
        font-size: 0.78rem;
        color: #999;
        text-align: right;
        margin-top: 2px;
    }

    .voyager .support-module .support-char-counter--warn {
        color: #e67e22;
    }

    .voyager .support-module .support-char-counter--danger {
        color: #e74c3c;
        font-weight: 600;
    }

    /* --- Seccion visual en formularios --- */
    .voyager .support-module .support-form-section {
        margin-bottom: 24px;
    }

    .voyager .support-module .support-form-section-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: #555;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding-bottom: 8px;
        border-bottom: 1px solid #e0e0e0;
        margin-bottom: 16px;
    }

    .voyager .support-module .support-form-section--optional .support-form-section-title {
        color: #999;
    }

    .voyager .support-module .support-area-guidance {
        color: #777;
    }

    .voyager .support-module .support-area-guidance-list {
        padding-left: 1.15rem;
        color: #888;
    }

    .voyager .support-module .support-area-guidance-list li {
        margin-bottom: 0.15rem;
    }

    .voyager .support-module .support-area-guidance-list li:last-child {
        margin-bottom: 0;
    }

    /* --- Stepper visual para formulario de receptores --- */
    .voyager .support-module .support-stepper {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
    }

    .voyager .support-module .support-stepper-step {
        flex: 1;
        padding: 8px 12px;
        background: #f0f0f0;
        border-radius: 4px;
        font-size: 0.85rem;
        color: #999;
        text-align: center;
        transition: all 0.2s;
    }

    .voyager .support-module .support-stepper-step--active {
        background: var(--support-color-open);
        color: #fff;
        font-weight: 600;
    }

    .voyager .support-module .support-stepper-step--done {
        background: var(--support-color-resolved);
        color: #fff;
    }

    /* --- Primario estrella --- */
    .voyager .support-module .support-star-primary {
        color: #f1c40f;
        font-size: 1.2rem;
    }

    /* --- Estado activo check / cross --- */
    .voyager .support-module .support-check-active {
        color: var(--support-color-resolved);
        font-weight: 700;
        font-size: 1.1rem;
    }

    .voyager .support-module .support-cross-inactive {
        color: #e74c3c;
        font-weight: 700;
        font-size: 1.1rem;
    }

    /* --- Badge receptor ambito --- */
    .voyager .support-module .support-receiver-scope-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 0.72rem;
        font-weight: 600;
        color: #fff;
        white-space: nowrap;
    }

    .voyager .support-module .support-receiver-scope-badge--global {
        background: #27ae60;
    }

    .voyager .support-module .support-receiver-scope-badge--ss {
        background: #2980b9;
    }

    /* --- Transiciones suaves en wraps de formulario --- */
    .voyager .support-module #wrap_ss {
        transition: opacity 0.25s ease, max-height 0.3s ease;
        overflow: hidden;
    }

    /* --- Detalle: comentario + visibilidad en una fila (admin) --- */
    .voyager .support-module .support-comment-vis-row>.support-comment-vis-col-comment {
        margin-bottom: 15px;
    }

    @media (min-width: 768px) {
        .voyager .support-module .support-comment-vis-row>.support-comment-vis-col-comment {
            margin-bottom: 0;
        }
    }

    /* --- Responsive: card view en mobile --- */
    @media (max-width: 767px) {
        .voyager .support-module .table-responsive {
            border: 0;
        }

        .voyager .support-module .table thead {
            display: none;
        }

        .voyager .support-module .table tbody tr {
            display: block;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            padding: 10px;
        }

        .voyager .support-module .table tbody tr td {
            display: block;
            border: 0;
            padding: 4px 0;
            text-align: right;
            font-size: 0.88rem;
        }

        .voyager .support-module .table tbody tr td::before {
            content: attr(data-label);
            float: left;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            font-size: 0.72rem;
        }

        .voyager .support-module .support-toolbar .form-control {
            max-width: 100%;
        }

        .voyager .support-module .support-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .voyager .support-module .support-toolbar-form {
            flex-wrap: wrap;
            flex: none;
            width: 100%;
        }

        .voyager .support-module .support-toolbar .support-toolbar-search {
            flex: 1 1 100%;
            max-width: none;
            width: 100%;
        }
    }
</style>
