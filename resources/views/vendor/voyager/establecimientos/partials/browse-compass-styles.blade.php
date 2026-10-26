<style>
    {{-- Tabs Compass: public/css/centinela-compass-ui.css (.cen-compass-ui) --}} .voyager .establecimientos-compass .sync-run-status {
        font-size: 11px;
        text-transform: uppercase;
    }

    {{-- .cen-browse-header*: centinela-compass-ui.css --}} .voyager .cen-browse-header--no-toolbar {
        margin-bottom: 12px;
        padding-bottom: 0;
        border-bottom: none;
    }

    .voyager .cen-browse-header__top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }

    .voyager .cen-browse-header__toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px 20px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #f8fbff 0%, #f6f8fa 100%);
        border: 1px solid #e8edf2;
        border-radius: 6px;
    }

    .voyager .cen-browse-toolbar-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }

    .voyager .cen-browse-toolbar-group--tools {
        margin-left: auto;
    }

    .voyager .cen-browse-toolbar-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #8a9bb0;
    }

    .voyager .cen-browse-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .voyager .cen-browse-inline-form {
        display: inline-block;
        margin: 0;
    }

    .voyager .cen-browse-soft-delete-toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        padding: 6px 10px;
        font-weight: 600;
        font-size: 12px;
        color: #5a6b7d;
        background: #fff;
        border: 1px solid #dde3ea;
        border-radius: 4px;
        cursor: pointer;
    }

    .voyager .cen-browse-soft-delete-toggle__text {
        line-height: 1.2;
    }

    .voyager .establecimientos-compass #cen-est-list-table tbody td {
        vertical-align: middle !important;
        border-color: #f0f3f6 !important;
    }

    .voyager .establecimientos-compass #cen-est-list-table tbody tr:hover {
        background: #f8fbff;
    }

    .voyager .establecimientos-compass .cen-browse-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-top: 1px solid #f0f3f6;
        font-size: 13px;
        color: #6b7c93;
    }

    @media (max-width: 992px) {
        .voyager .cen-browse-toolbar-group--tools {
            margin-left: 0;
            width: 100%;
        }
    }

    .voyager .cen-browse-establecimientos-panel {
        padding: 0 15px 20px;
    }

    .voyager .cen-browse-establecimientos-list-section {
        margin-top: 4px;
    }

    .voyager .cen-read-section__head--with-actions .cen-est-list-head-actions {
        margin-left: auto;
        flex-shrink: 0;
    }

    .voyager .cen-read-section__head--with-actions .cen-est-list-head-actions .btn {
        white-space: nowrap;
    }

    .voyager .cen-browse-establecimientos-list-section .cen-read-table-wrap {
        border-top: 1px solid #f0f3f6;
    }

    .voyager .cen-browse-establecimientos-filters-standalone {
        max-width: 420px;
        margin: 0 16px 16px;
        padding: 16px 18px;
        border: 1px solid #e8edf2;
        border-radius: 8px;
        background: #fafbfc;
    }

    .voyager .cen-browse-establecimientos-filters-standalone .cen-browse-establecimientos-filters {
        border: none;
        padding: 0;
        background: transparent;
    }

    .voyager .cen-browse-establecimientos-filters-pendiente {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e8edf2;
    }

    .voyager .cen-read-empty--compact {
        padding: 24px 16px;
    }

    .voyager #cen-est-list-table tbody td {
        vertical-align: middle;
    }

    .voyager #cen-est-list-table thead th.cen-est-list-sortable {
        padding: 0 !important;
    }

    .voyager .cen-est-list-sort {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        width: 100%;
        margin: 0;
        padding: 10px 14px;
        border: 0;
        background: transparent;
        font: inherit;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #8a9bb0;
        text-align: inherit;
        cursor: pointer;
        white-space: nowrap;
    }

    .voyager #cen-est-list-table thead th.text-center .cen-est-list-sort {
        justify-content: center;
    }

    .voyager .cen-est-list-sort:hover,
    .voyager .cen-est-list-sort:focus-visible {
        color: #22a7f0;
        outline: none;
    }

    .voyager .cen-est-list-sort__icons {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        line-height: 0.65;
        flex-shrink: 0;
    }

    .voyager .cen-est-list-sort__icon {
        font-size: 11px;
        opacity: 0.35;
        transition: opacity 0.15s ease, color 0.15s ease;
    }

    .voyager .cen-est-list-sort.is-active .cen-est-list-sort__icon {
        opacity: 0.35;
    }

    .voyager .cen-est-list-sort.is-active.is-asc .cen-est-list-sort__icon--up,
    .voyager .cen-est-list-sort.is-active.is-desc .cen-est-list-sort__icon--down {
        opacity: 1;
        color: #22a7f0;
    }

    .voyager #cen-est-list-table .bread-actions {
        white-space: nowrap;
    }

    .voyager .cen-est-list-establecimiento {
        display: flex;
        flex-direction: column;
        gap: 4px;
        align-items: flex-start;
    }

    .voyager .cen-est-list-establecimiento__nombre {
        font-weight: 600;
        color: #2c3e50;
    }

    .voyager .cen-est-list-establecimiento__madre {
        font-size: 12px;
        color: #6b7c93;
        line-height: 1.35;
    }

    .voyager .cen-est-list-establecimiento__badge {
        margin-left: 0 !important;
    }

    .voyager .cen-est-geo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-size: 15px;
    }

    .voyager .cen-est-geo--ok {
        color: #1a6fb5;
        background: #e8f4fc;
    }

    .voyager .cen-est-geo--invalid {
        color: #b45309;
        background: #fff4e0;
    }

    .voyager .cen-browse-establecimientos-layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0;
        align-items: stretch;
        border: 1px solid #e8edf2;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }

    @media (min-width: 993px) {
        .voyager .cen-browse-establecimientos-layout {
            grid-template-columns: 3fr 2fr;
        }
    }

    .voyager .cen-browse-establecimientos-layout__map {
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 360px;
    }

    .voyager .cen-browse-establecimientos-layout .cen-browse-establecimientos {
        border-radius: 0;
    }

    .voyager .cen-browse-establecimientos-layout .cen-browse-establecimientos-legend {
        border-top: 1px solid #f0f3f6;
        flex-shrink: 0;
    }

    .voyager .cen-browse-establecimientos-filters {
        padding: 16px 18px;
        border-top: 1px solid #e8edf2;
        background: #fafbfc;
        min-width: 0;
    }

    @media (min-width: 993px) {
        .voyager .cen-browse-establecimientos-filters {
            border-top: none;
            border-left: 1px solid #e8edf2;
            max-height: none;
            overflow: visible;
        }
    }

    .voyager .cen-browse-establecimientos-filters__title {
        margin: 0 0 14px;
        font-size: 14px;
        font-weight: 600;
        color: #3d4f63;
    }

    .voyager .cen-browse-establecimientos-filters__title i {
        margin-right: 6px;
        color: #1a6fb5;
    }

    .voyager .cen-browse-establecimientos-filters__form .form-group {
        margin-bottom: 12px;
    }

    .voyager .cen-browse-establecimientos-filters__form label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7c93;
        margin-bottom: 4px;
    }

    .voyager .cen-browse-establecimientos-filters__form .form-control {
        font-size: 13px;
        height: 34px;
    }

    .voyager .cen-browse-establecimientos-filters__establishments .help-block {
        margin: 6px 0 0;
        font-size: 11px;
        color: #8a9bb0;
    }

    .voyager .cen-browse-establecimientos-filters__establishments .select2-container {
        width: 100% !important;
    }

    .voyager .cen-browse-establecimientos-filters__establishments .select2-selection--multiple {
        min-height: 38px;
        border-color: #dce4ec;
    }

    .voyager .cen-browse-establecimientos-filters__establishments .select2-selection__choice {
        font-size: 12px;
        background: #e8f4fc;
        border-color: #c5dff0;
        color: #1a5276;
    }

    .voyager .cen-browse-establecimientos-filters__establishments .select2-search__field {
        font-size: 13px;
    }

    .voyager .cen-browse-establecimientos-filters__count {
        margin: 12px 0 0;
        font-size: 12px;
        color: #6b7c93;
        text-align: center;
    }

    .voyager .cen-browse-establecimientos {
        width: 100%;
        flex: 1 1 auto;
        height: 420px;
        min-height: 360px;
        background: #e8edf2;
        z-index: 1;
    }

    @media (min-width: 993px) {
        .voyager .cen-browse-establecimientos-layout__map {
            min-height: 560px;
        }

        .voyager .cen-browse-establecimientos {
            height: auto;
            min-height: 480px;
        }
    }

    .voyager .cen-browse-establecimientos-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin: 0;
        padding: 12px 16px 16px;
        font-size: 12px;
        color: #6b7c93;
    }

    .voyager .cen-browse-establecimientos-legend__item,
    .voyager .cen-browse-establecimientos-legend__toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .voyager .cen-browse-establecimientos-filters__form .cen-browse-establecimientos-legend__toggle {
        width: 100%;
        justify-content: center;
    }

    .voyager .cen-browse-establecimientos-legend__toggle {
        margin: 0;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #6b7c93;
        background: #fff;
        border: 1px solid #dce4ec;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .voyager .cen-browse-establecimientos-legend__toggle:hover {
        border-color: #c5dff0;
        color: #1a5276;
        background: #f8fbff;
    }

    .voyager .cen-browse-establecimientos-legend__toggle[aria-pressed='true'] {
        color: #7a5a00;
        background: #fff8e6;
        border-color: #e8c96a;
        box-shadow: inset 0 0 0 1px #f0d78a;
    }

    .voyager .cen-browse-establecimientos-legend__toggle:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        background: #f4f6f8;
        border-color: #e8edf2;
        color: #a8b5c4;
        box-shadow: none;
    }

    .voyager .cen-browse-establecimientos-legend__toggle:disabled:hover {
        background: #f4f6f8;
        border-color: #e8edf2;
        color: #a8b5c4;
    }

    .voyager .cen-browse-establecimientos-legend__dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
    }

    .voyager .cen-browse-establecimientos-legend__dot--default {
        background: #1a6fb5;
    }

    .voyager .cen-browse-establecimientos-legend__dot--pending {
        background: #e8a317;
    }

    .voyager .cen-map-marker {
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
    }

    .voyager .cen-map-marker--default {
        background: #1a6fb5;
    }

    .voyager .cen-map-marker--pending {
        background: #e8a317;
    }

    .voyager .cen-browse-ss-spotlight__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }

    .voyager .cen-browse-ss-spotlight .cen-read-spotlight__label {
        margin: 0;
    }

    .voyager .cen-browse-ss-spotlight__badge {
        display: inline-block;
        padding: 2px 8px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #1a6fb5;
        background: #eef6fc;
        border: 1px solid #d4e8f7;
        border-radius: 999px;
    }

    .voyager .cen-browse-ss-spotlight--exploring {
        border-left-color: #3d8bcd;
        background: linear-gradient(135deg, #f8fbff 0%, #fff 100%);
    }

    .voyager .cen-browse-ss-form {
        margin: 0;
    }

    .voyager .cen-browse-ss-form__label {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .voyager .cen-browse-ss-select {
        display: block;
        width: 100%;
        max-width: 36rem;
        margin: 0;
        padding: 6px 36px 6px 10px;
        font-size: 17px;
        font-weight: 600;
        line-height: 1.3;
        color: #2a3f54;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%235a6b7d' d='M1.2 1.4L6 6.2l4.8-4.8'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        border: 1px solid #dde3ea;
        border-radius: 6px;
        box-shadow: none;
        appearance: none;
        -webkit-appearance: none;
    }

    .voyager .cen-browse-ss-select:hover,
    .voyager .cen-browse-ss-select:focus {
        border-color: #62a8ea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(98, 168, 234, 0.18);
    }

    .voyager .cen-browse-ss-spotlight .cen-read-spotlight__hint {
        margin-top: 8px;
    }

    .voyager .cen-browse-ss-spotlight__dot {
        margin: 0 4px;
        color: #c5d0db;
    }
</style>
