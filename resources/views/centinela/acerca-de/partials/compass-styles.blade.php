{{-- Acerca de ↔ kit Compass Centinela (cen-compass-ui.css). SDD-214 --}}
<style>
    .voyager .acerca-de-compass.cen-compass-ui,
    .voyager .releases-compass.cen-compass-ui {
        --acerca-de-anchor-offset: calc(5.25rem + 20px);
        scroll-behavior: smooth;
    }

    .voyager .acerca-de-compass.cen-compass-ui .centinela-title-banner,
    .voyager .releases-compass.cen-compass-ui .centinela-title-banner {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 14px;
        margin: 0 0 1.25rem;
        padding: 1rem 0 1.25rem;
    }

    .voyager .acerca-de-compass.cen-compass-ui .centinela-title-banner svg,
    .voyager .releases-compass.cen-compass-ui .centinela-title-banner svg {
        flex-shrink: 0;
    }

    .voyager .acerca-de-compass.cen-compass-ui .centinela-title-banner__label,
    .voyager .releases-compass.cen-compass-ui .centinela-title-banner__label {
        font-family: 'Arial Black', Gadget, sans-serif;
        font-weight: 900;
        font-size: clamp(1.75rem, 5vw, 2.75rem);
        color: #eb3c46;
        line-height: 1.1;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-subnav {
        margin-bottom: 0;
    }

    .voyager .acerca-de-compass.cen-compass-ui .cen-dashboard-block,
    .voyager .releases-compass.cen-compass-ui .cen-dashboard-block {
        margin-top: 0;
    }

    .voyager .acerca-de-compass.cen-compass-ui .cen-dashboard-block__head,
    .voyager .releases-compass.cen-compass-ui .cen-dashboard-block__head {
        padding: 12px 20px;
    }

    .voyager .acerca-de-compass.cen-compass-ui .cen-dashboard-block__body,
    .voyager .releases-compass.cen-compass-ui .cen-dashboard-block__body {
        padding: 20px 24px 24px;
    }

    /* Prose compartido: páginas markdown y cuerpo de releases */
    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown,
    .voyager .releases-compass.cen-compass-ui .release-body {
        font-size: 14px;
        line-height: 1.65;
        color: var(--cen-text, #2a3f54);
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown> :first-child,
    .voyager .releases-compass.cen-compass-ui .release-body> :first-child {
        margin-top: 0;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown> :last-child,
    .voyager .releases-compass.cen-compass-ui .release-body> :last-child {
        margin-bottom: 0;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown p,
    .voyager .releases-compass.cen-compass-ui .release-body p {
        margin: 0 0 0.875rem;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown h1,
    .voyager .releases-compass.cen-compass-ui .release-body h1 {
        font-size: 1.25rem;
        margin: 0 0 0.75rem;
        font-weight: 600;
        line-height: 1.35;
        color: #3d5166;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown h2,
    .voyager .releases-compass.cen-compass-ui .release-body h2 {
        font-size: 1.0625rem;
        margin: 1.5rem 0 0.625rem;
        font-weight: 600;
        line-height: 1.35;
        color: #3d5166;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown h2:first-child,
    .voyager .releases-compass.cen-compass-ui .release-body h2:first-child {
        margin-top: 0;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown h2:not(:first-child),
    .voyager .releases-compass.cen-compass-ui .release-body h2:not(:first-child) {
        padding-top: 1.25rem;
        border-top: 1px solid var(--cen-border-light, #f0f3f6);
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown h3,
    .voyager .releases-compass.cen-compass-ui .release-body h3 {
        font-size: 0.9375rem;
        margin: 1.125rem 0 0.5rem;
        font-weight: 600;
        line-height: 1.35;
        color: #4a5f73;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown :is(h1, h2, h3, h4, h5, h6),
    .voyager .releases-compass.cen-compass-ui .release-body :is(h1, h2, h3, h4, h5, h6) {
        scroll-margin-top: var(--acerca-de-anchor-offset);
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown ul,
    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown ol,
    .voyager .releases-compass.cen-compass-ui .release-body ul,
    .voyager .releases-compass.cen-compass-ui .release-body ol {
        margin: 0 0 0.875rem;
        padding-left: 1.35rem;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown li,
    .voyager .releases-compass.cen-compass-ui .release-body li {
        margin-bottom: 0.35rem;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown li>p,
    .voyager .releases-compass.cen-compass-ui .release-body li>p {
        margin-bottom: 0.35rem;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown a,
    .voyager .releases-compass.cen-compass-ui .release-body a {
        color: var(--cen-accent, #62a8ea);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown a:hover,
    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown a:focus,
    .voyager .releases-compass.cen-compass-ui .release-body a:hover,
    .voyager .releases-compass.cen-compass-ui .release-body a:focus {
        color: #4a96d9;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown strong,
    .voyager .releases-compass.cen-compass-ui .release-body strong {
        font-weight: 600;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown hr,
    .voyager .releases-compass.cen-compass-ui .release-body hr {
        margin: 1.25rem 0;
        border: 0;
        border-top: 1px solid var(--cen-border, #e8edf2);
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown code:not(pre code),
    .voyager .releases-compass.cen-compass-ui .release-body code:not(pre code) {
        padding: 0.1em 0.35em;
        font-size: 0.92em;
        background: #f3f5f7;
        border: 1px solid var(--cen-border-light, #f0f3f6);
        border-radius: 3px;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown pre.mermaid,
    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown pre code,
    .voyager .releases-compass.cen-compass-ui .release-body pre {
        background: #f7f9fb;
        border: 1px solid var(--cen-border, #e8edf2);
        border-radius: 4px;
        padding: 0.875rem 1rem;
        overflow-x: auto;
        display: block;
        white-space: pre-wrap;
        margin: 0 0 1rem;
        font-size: 13px;
        line-height: 1.5;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown table,
    .voyager .releases-compass.cen-compass-ui .release-body table,
    .voyager .releases-compass.cen-compass-ui .acerca-de-markdown table {
        width: 100%;
        margin: 0 0 1rem;
        border-collapse: collapse;
        font-size: 13px;
        line-height: 1.45;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown th,
    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown td,
    .voyager .releases-compass.cen-compass-ui .release-body th,
    .voyager .releases-compass.cen-compass-ui .release-body td {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--cen-border, #e8edf2);
        text-align: left;
        vertical-align: top;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown thead th,
    .voyager .releases-compass.cen-compass-ui .release-body thead th {
        background: var(--cen-bg-section-head, #f6f8fa);
        font-weight: 600;
        color: #4a5f73;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown tbody tr:nth-child(even),
    .voyager .releases-compass.cen-compass-ui .release-body tbody tr:nth-child(even) {
        background: #fafbfc;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown img[src*="/docs/diagrams/"] {
        display: block;
        max-width: 100%;
        height: auto;
        margin: 0.75rem 0 1rem;
        border: 1px solid var(--cen-border, #e8edf2);
        border-radius: 4px;
        background: #fff;
    }

    /* Notas/citas: anula blockquote de Bootstrap (~17.5px) */
    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown blockquote {
        margin: 0.5rem 0 1rem;
        padding: 0.5rem 0.75rem;
        font-size: 12px;
        line-height: 1.5;
        color: var(--cen-text-muted, #6b7c93);
        border-left: 3px solid var(--cen-border, #e8edf2);
        background: #fafbfc;
    }

    .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown blockquote p {
        margin: 0;
        font-size: inherit;
        line-height: inherit;
        color: inherit;
    }

    .voyager .releases-compass.cen-compass-ui .releases-alert {
        margin: 0 0 1rem;
    }

    .voyager .releases-compass.cen-compass-ui .releases-alert:last-child {
        margin-bottom: 0;
    }

    .voyager .releases-compass.cen-compass-ui .release-empty {
        margin: 0;
        color: var(--cen-text-muted, #6b7c93);
        line-height: 1.6;
    }

    .voyager .releases-compass.cen-compass-ui .release-block {
        margin-bottom: 0.625rem;
        border: 1px solid var(--cen-border, #e8edf2);
        border-radius: 4px;
        padding: 0.375rem 1rem;
        background: #fff;
    }

    .voyager .releases-compass.cen-compass-ui .release-block:last-child {
        margin-bottom: 0;
    }

    .voyager .releases-compass.cen-compass-ui .release-block summary {
        cursor: pointer;
        font-weight: 600;
        padding: 0.5rem 0;
        line-height: 1.45;
        color: var(--cen-text, #2a3f54);
    }

    .voyager .releases-compass.cen-compass-ui .release-block .release-meta {
        font-weight: 400;
        color: var(--cen-text-muted, #6b7c93);
        font-size: 0.9em;
    }

    .voyager .releases-compass.cen-compass-ui .release-body {
        padding: 0.625rem 0 0.875rem;
        border-top: 1px solid var(--cen-border-light, #f0f3f6);
        margin-top: 0.25rem;
        overflow-x: auto;
    }

    .voyager .releases-compass.cen-compass-ui .release-block[id] {
        scroll-margin-top: var(--acerca-de-anchor-offset);
    }

    @media (max-width: 767px) {

        .voyager .acerca-de-compass.cen-compass-ui .cen-dashboard-block__head,
        .voyager .releases-compass.cen-compass-ui .cen-dashboard-block__head {
            padding: 10px 14px;
        }

        .voyager .acerca-de-compass.cen-compass-ui .cen-dashboard-block__body,
        .voyager .releases-compass.cen-compass-ui .cen-dashboard-block__body {
            padding: 16px;
        }

        .voyager .acerca-de-compass.cen-compass-ui .acerca-de-markdown,
        .voyager .releases-compass.cen-compass-ui .release-body {
            font-size: 13px;
        }
    }
</style>
