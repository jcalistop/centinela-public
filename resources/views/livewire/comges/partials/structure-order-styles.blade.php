<style>
    .voyager .cen-compass-ui.comges-structure-order-page .comges-structure-order-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .voyager .cen-compass-ui .comges-structure-order-hint {
        margin-bottom: 1.25rem;
        font-size: 13px;
        line-height: 1.5;
        border-color: #bce8f1;
    }

    .voyager .cen-compass-ui .comges-structure-order-hint i {
        margin-right: 0.35rem;
    }

    .voyager .cen-compass-ui #comges-structure-sort-root {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .voyager .cen-compass-ui .comges-structure-commitment-panel {
        margin: 0;
    }

    .voyager .cen-compass-ui .comges-structure-panel-heading {
        cursor: pointer;
        margin: 0;
        user-select: none;
    }

    .voyager .cen-compass-ui .comges-structure-panel-heading:hover {
        background: linear-gradient(135deg, #f3f8fe 0%, #eef3f8 100%);
    }

    .voyager .cen-compass-ui .comges-structure-panel-heading__main {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        min-width: 0;
    }

    .voyager .cen-compass-ui .comges-structure-panel-heading__text {
        min-width: 0;
        flex: 1 1 auto;
    }

    .voyager .cen-compass-ui .comges-structure-chevron {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        margin-top: 0.15rem;
        color: var(--cen-meta, #8a9bb0);
        transition: transform 0.2s ease;
        flex-shrink: 0;
    }

    .voyager .cen-compass-ui .comges-structure-commitment-panel:has(.panel-collapse.in) .comges-structure-chevron {
        transform: rotate(90deg);
    }

    .voyager .cen-compass-ui .comges-structure-commitment-clave {
        display: inline-block;
        margin-bottom: 0.35rem;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        border: 1px solid var(--cen-border, #e8edf2);
        background: #fff;
        color: var(--cen-accent, #62a8ea);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .voyager .cen-compass-ui .comges-structure-commitment-title {
        font-size: 16px;
        line-height: 1.35;
        margin: 0;
    }

    .voyager .cen-compass-ui .comges-structure-collapsed-hint {
        display: block;
        margin-top: 0.25rem;
        font-size: 11px;
        color: var(--cen-meta, #8a9bb0);
        font-style: italic;
    }

    .voyager .cen-compass-ui .comges-structure-commitment-panel:has(.panel-collapse.in) .comges-structure-collapsed-hint {
        display: none;
    }

    .voyager .cen-compass-ui .comges-structure-indicator-count {
        flex-shrink: 0;
        align-self: center;
        background: #fff;
        border: 1px solid var(--cen-border, #e8edf2);
        color: var(--cen-text, #2a3f54);
        font-size: 11px;
        font-weight: 600;
        padding: 0.35rem 0.6rem;
    }

    .voyager .cen-compass-ui .comges-structure-panel-body {
        padding: 0.75rem 1rem 1rem;
        background: #fff;
    }

    .voyager .cen-compass-ui .comges-structure-sort-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        min-height: 2.5rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .voyager .cen-compass-ui .comges-structure-sort-item {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        padding: 0.65rem 0.75rem;
        border: 1px solid var(--cen-border, #e8edf2);
        border-radius: 6px;
        background: var(--cen-bg-section-head, #f6f8fa);
        cursor: grab;
        user-select: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .voyager .cen-compass-ui .comges-structure-sort-item:hover {
        border-color: #c5d9ef;
        box-shadow: 0 1px 4px rgba(98, 168, 234, 0.12);
    }

    .voyager .cen-compass-ui .comges-structure-sort-item:active {
        cursor: grabbing;
    }

    .voyager .cen-compass-ui .comges-sort-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        margin-top: 0.1rem;
        border-radius: 4px;
        background: #fff;
        border: 1px solid var(--cen-border, #e8edf2);
        color: var(--cen-meta, #8a9bb0);
        flex-shrink: 0;
        pointer-events: none;
    }

    .voyager .cen-compass-ui .comges-structure-sort-item__content {
        flex: 1 1 auto;
        min-width: 0;
    }

    .voyager .cen-compass-ui .comges-structure-indicator-clave {
        display: inline-block;
        margin-bottom: 0.25rem;
        padding: 0.1rem 0.45rem;
        border-radius: 4px;
        background: #fff;
        border: 1px solid var(--cen-border, #e8edf2);
        color: var(--cen-text, #2a3f54);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.4;
    }

    .voyager .cen-compass-ui .comges-structure-indicator-nombre {
        display: block;
        font-size: 13px;
        line-height: 1.45;
        color: var(--cen-text-muted, #6b7c93);
        word-wrap: break-word;
        overflow-wrap: anywhere;
    }

    .voyager .cen-compass-ui .comges-structure-sort-ghost {
        opacity: 0.55;
        background: #e8f4fc;
        border-style: dashed;
    }

    .voyager .cen-compass-ui .comges-structure-empty-hint {
        margin: 0.5rem 0 0;
        padding: 0.75rem;
        border: 1px dashed var(--cen-border, #e8edf2);
        border-radius: 6px;
        background: #fafbfc;
        color: var(--cen-meta, #8a9bb0);
        font-size: 12px;
        font-style: italic;
        text-align: center;
    }

    .voyager .cen-compass-ui .comges-structure-commitment-panel.comges-structure-drop-target .comges-structure-panel-heading {
        background: #e8f4fc;
        outline: 2px dashed var(--cen-accent, #62a8ea);
        outline-offset: -2px;
    }

    body.comges-structure-sorting {
        cursor: grabbing;
    }

    body.comges-structure-sorting .comges-structure-sort-item {
        cursor: grabbing;
    }
</style>
