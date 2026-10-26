<style>
    .voyager .cen-read-detail {
        padding: 0 15px 20px;
    }

    {{-- Hero / sección / grid base: public/css/centinela-compass-ui.css (.cen-compass-ui) --}} .voyager .cen-read-hero__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .voyager .cen-read-hero__meta {
        flex: 0 0 auto;
        text-align: right;
        min-width: 140px;
    }

    .voyager .cen-read-hero__meta dt {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #8a9bb0;
        margin: 0;
    }

    .voyager .cen-read-hero__meta dd {
        margin: 2px 0 10px;
        font-size: 13px;
        font-weight: 600;
        color: #2a3f54;
    }

    .voyager .cen-read-ubicacion-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 18px;
        align-items: stretch;
    }

    .voyager .cen-read-ubicacion-row__col {
        min-width: 0;
    }

    .voyager .cen-read-ubicacion-row__col .cen-read-section {
        margin-bottom: 0;
        height: 100%;
    }

    @media (max-width: 992px) {
        .voyager .cen-read-ubicacion-row {
            grid-template-columns: 1fr;
        }

        .voyager .cen-read-ubicacion-row__col .cen-read-section {
            margin-bottom: 18px;
        }

        .voyager .cen-read-ubicacion-row__col:last-child .cen-read-section {
            margin-bottom: 0;
        }
    }

    .voyager .cen-read-ubicacion-map-section {
        overflow: visible;
    }

    .voyager .cen-read-ubicacion-map-section__body {
        padding: 12px 16px 16px;
        overflow: visible;
    }

    .voyager .cen-read-ubicacion-map {
        width: 100%;
        height: 320px;
        border-radius: 6px;
        border: 1px solid #dce8f5;
        background: #f0f3f6;
        z-index: 1;
    }

    .voyager .cen-read-ubicacion-map .leaflet-pane,
    .voyager .cen-read-ubicacion-map .leaflet-top,
    .voyager .cen-read-ubicacion-map .leaflet-bottom {
        z-index: 1;
    }

    @media (min-width: 1200px) {
        .voyager .cen-read-ubicacion-map {
            height: 380px;
        }
    }

    .voyager .cen-read-ubicacion-map__hint,
    .voyager .cen-read-ubicacion-map__unavailable {
        margin: 10px 0 0;
        font-size: 12px;
        padding: 0 4px;
    }

    .voyager .cen-read-ubicacion-map__unavailable {
        margin-bottom: 8px;
    }

    @media (max-width: 768px) {
        .voyager .cen-read-hero__meta {
            text-align: left;
            width: 100%;
        }
    }

    .voyager .cen-read-grid .cen-read-item {
        border-right: 1px solid #f0f3f6;
    }

    .voyager .cen-read-grid .cen-read-item:nth-child(2n) {
        border-right: none;
    }

    .voyager .cen-read-grid:not(.cen-read-grid--2col) .cen-read-item {
        border-right: none;
    }

    .voyager .cen-read-grid .cen-read-item--wide {
        border-right: none;
    }

    .voyager .cen-read-grid .cen-read-item:last-child,
    .voyager .cen-read-grid .cen-read-item:nth-last-child(-n+2):nth-child(2n+1) {
        border-bottom: none;
    }

    .voyager .cen-read-item--wide {
        grid-column: 1 / -1;
    }

    .voyager .cen-read-item__label {
        display: block;
        margin: 0 0 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #8a9bb0;
    }

    .voyager .cen-read-item__value {
        margin: 0;
        font-size: 14px;
        color: #2a3f54;
        line-height: 1.45;
        word-break: break-word;
    }

    .voyager .cen-read-item__value--highlight {
        font-size: 16px;
        font-weight: 600;
        color: #1a6fb5;
    }

    .voyager .cen-read-item__value--muted {
        color: #9aa8b8;
        font-style: italic;
    }

    .voyager .cen-read-badge {
        display: inline-block;
        padding: 3px 10px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 20px;
        line-height: 1.4;
    }

    .voyager .cen-read-badge--info {
        background: #e8f4fc;
        color: #1a6fb5;
        border: 1px solid #c5e3f7;
    }

    .voyager .cen-read-badge--success {
        background: #e8f6ee;
        color: #2d7a46;
        border: 1px solid #b8e6c8;
    }

    .voyager .cen-read-badge--warning {
        background: #fff8e6;
        color: #9a7610;
        border: 1px solid #f5e0a8;
    }

    .voyager .cen-read-badge--danger {
        background: #fdecea;
        color: #b52b27;
        border: 1px solid #f5c4c0;
    }

    .voyager .cen-read-badge--neutral {
        background: #f0f3f6;
        color: #5a6b7d;
        border: 1px solid #dde3ea;
    }

    .voyager .cen-read-spotlight {
        padding: 16px 18px;
        margin-bottom: 18px;
        background: #fff;
        border-left: 4px solid #62a8ea;
        border-radius: 0 6px 6px 0;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    }

    .voyager .cen-read-spotlight__label {
        margin: 0 0 4px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #8a9bb0;
        font-weight: 600;
    }

    .voyager .cen-read-spotlight__value {
        margin: 0;
        font-size: 17px;
        font-weight: 600;
        color: #2a3f54;
    }

    .voyager .cen-read-spotlight__hint {
        margin: 6px 0 0;
        font-size: 12px;
        color: #8a9bb0;
    }

    .voyager .cen-read-spotlight--pending {
        border-left-color: #e8a317;
        background: linear-gradient(135deg, #fffdf5 0%, #fff8e6 100%);
    }

    .voyager .cen-read-breadcrumb {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: 14px;
    }

    .voyager .cen-read-breadcrumb li {
        color: #6b7c93;
    }

    .voyager .cen-read-breadcrumb li:not(:last-child)::after {
        content: '›';
        margin-left: 8px;
        color: #c5ced8;
    }

    .voyager .cen-read-breadcrumb li:last-child {
        font-weight: 600;
        color: #2a3f54;
    }

    .voyager .cen-read-empty {
        text-align: center;
        padding: 40px 24px;
        color: #8a9bb0;
    }

    .voyager .cen-read-empty i {
        display: block;
        font-size: 40px;
        margin-bottom: 12px;
        color: #c5ced8;
    }

    .voyager .cen-read-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
    }

    .voyager .cen-read-status-pill--success {
        background: #e8f6ee;
        color: #2d7a46;
    }

    .voyager .cen-read-status-pill--warning {
        background: #fff8e6;
        color: #9a7610;
    }

    .voyager .cen-read-status-pill--danger {
        background: #fdecea;
        color: #b52b27;
    }

    .voyager .cen-read-status-pill--neutral {
        background: #f0f3f6;
        color: #5a6b7d;
    }

    .voyager .cen-read-status-pill i {
        font-size: 18px;
    }

    .voyager .cen-read-intro {
        margin: 0 0 18px;
        padding: 12px 16px;
        font-size: 13px;
        color: #6b7c93;
        background: #f8fafc;
        border-radius: 6px;
        border: 1px solid #e8edf2;
        line-height: 1.5;
    }

    .voyager .cen-read-form-box {
        padding: 16px 18px;
        background: #fafbfc;
        border-radius: 0 0 6px 6px;
    }

    .voyager .cen-read-form-box .form-control {
        border-color: #dce3ea;
        box-shadow: none;
    }

    .voyager .cen-read-form-box .form-control:focus {
        border-color: #62a8ea;
    }

    .voyager .cen-read-form-box label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #8a9bb0;
    }

    .voyager .cen-read-note-list {
        list-style: none;
        margin: 0;
        padding: 8px 0 4px;
    }

    .voyager .cen-read-note-card {
        position: relative;
        margin: 0 16px 14px;
        padding: 14px 16px 14px 20px;
        background: #fff;
        border: 1px solid #e8edf2;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .voyager .cen-read-note-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: #62a8ea;
        border-radius: 6px 0 0 6px;
    }

    .voyager .cen-read-note-card--pending-on::before {
        background: #e8a317;
    }

    .voyager .cen-read-note-card--pending-off::before {
        background: #3d9a5d;
    }

    .voyager .cen-read-toggle {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        margin: 0;
        cursor: pointer;
        font-weight: 600;
        color: #4a5f73;
        user-select: none;
    }

    .voyager .cen-read-toggle input[type='checkbox'] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .voyager .cen-read-toggle__track {
        position: relative;
        flex: 0 0 44px;
        width: 44px;
        height: 24px;
        background: #dde3ea;
        border-radius: 12px;
        transition: background 0.2s ease;
    }

    .voyager .cen-read-toggle__track::after {
        content: '';
        position: absolute;
        top: 3px;
        left: 3px;
        width: 18px;
        height: 18px;
        background: #fff;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        transition: transform 0.2s ease;
    }

    .voyager .cen-read-toggle input:checked+.cen-read-toggle__track {
        background: #e8a317;
    }

    .voyager .cen-read-toggle input:checked+.cen-read-toggle__track::after {
        transform: translateX(20px);
    }

    .voyager .cen-read-toggle__text {
        font-size: 14px;
    }

    .voyager .cen-read-note-card__meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .voyager .cen-read-note-card__date {
        font-size: 12px;
        font-weight: 600;
        color: #4a5f73;
    }

    .voyager .cen-read-note-card__user {
        font-size: 12px;
        color: #8a9bb0;
    }

    .voyager .cen-read-note-card__body {
        margin: 0;
        font-size: 14px;
        color: #2a3f54;
        line-height: 1.55;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .voyager .cen-read-table-wrap {
        padding: 10px 0 8px;
        overflow-x: auto;
    }

    .voyager .cen-read-data-table {
        width: 100%;
        margin: 0;
        font-size: 13px;
    }

    .voyager .cen-read-data-table thead th {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #8a9bb0;
        background: #f6f8fa;
        border-bottom: 2px solid #e8edf2 !important;
        padding: 10px 14px !important;
        white-space: nowrap;
    }

    .voyager .cen-read-data-table tbody td {
        padding: 12px 14px !important;
        vertical-align: middle !important;
        border-color: #f0f3f6 !important;
        color: #2a3f54;
    }

    .voyager .cen-read-data-table tbody tr:hover {
        background: #f8fbff;
    }

    .voyager .cen-read-data-table code {
        font-size: 11px;
        padding: 2px 6px;
        background: #f0f3f6;
        border-radius: 4px;
        color: #4a5f73;
    }

    .voyager .cen-read-data-table .cen-read-changes {
        font-size: 12px;
        color: #5a6b7d;
        line-height: 1.45;
        max-width: 420px;
    }

    .voyager .cen-read-event-badge {
        display: inline-block;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        border-radius: 4px;
        letter-spacing: 0.03em;
    }

    .voyager .cen-read-event-badge--created {
        background: #e8f6ee;
        color: #2d7a46;
    }

    .voyager .cen-read-event-badge--updated {
        background: #e8f4fc;
        color: #1a6fb5;
    }

    .voyager .cen-read-event-badge--deleted {
        background: #fdecea;
        color: #b52b27;
    }

    .voyager .cen-read-event-badge--restored {
        background: #fff8e6;
        color: #9a7610;
    }

    .voyager .cen-read-event-badge--default {
        background: #f0f3f6;
        color: #5a6b7d;
    }

    .voyager .cen-read-severity {
        display: inline-block;
        padding: 3px 10px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        border-radius: 20px;
    }

    .voyager .cen-read-severity--error {
        background: #fdecea;
        color: #b52b27;
        border: 1px solid #f5c4c0;
    }

    .voyager .cen-read-severity--warning {
        background: #fff8e6;
        color: #9a7610;
        border: 1px solid #f5e0a8;
    }

    .voyager .cen-read-severity--default {
        background: #f0f3f6;
        color: #5a6b7d;
        border: 1px solid #dde3ea;
    }

    .voyager .cen-read-severity--success {
        background: #e8f6ee;
        color: #2d7a46;
        border: 1px solid #b8e6c8;
    }

    .voyager .cen-read-section__foot {
        padding: 10px 16px 14px;
        border-top: 1px solid #f0f3f6;
        text-align: right;
    }

    .voyager .cen-read-section__foot .pagination {
        margin: 0;
    }

    .voyager .cen-read-section__head--split {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .voyager .cen-read-section__head-main {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .voyager .cen-read-count-badge--muted {
        background: #8ea9c1;
    }

    .voyager .cen-equiv-promote-form-hidden {
        display: none;
    }

    .voyager .cen-equiv-promote-select {
        width: 100%;
        min-width: 180px;
        max-width: 320px;
    }

    .voyager .cen-equiv-establishment-cell {
        min-width: 140px;
        max-width: 220px;
    }

    .voyager .cen-equiv-establishment-cell__codigo {
        font-size: 12px;
        color: #2b3d4f;
    }

    .voyager .cen-equiv-establishment-cell__nombre {
        font-size: 12px;
        color: #5a6c7d;
        line-height: 1.35;
    }

    .voyager .cen-equiv-manual-form .pull-right {
        margin-top: 4px;
    }

    .voyager .cen-help-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        margin-left: 4px;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        color: #fff;
        background: #8fa4b8;
        border-radius: 50%;
        cursor: help;
        vertical-align: middle;
        user-select: none;
    }

    .voyager .cen-help-icon:hover,
    .voyager .cen-help-icon:focus {
        background: #62a8ea;
        outline: none;
    }

    .voyager .cen-read-count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        margin-left: 6px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: #62a8ea;
        border-radius: 11px;
        vertical-align: middle;
    }
</style>
