@php
    /** @var string|null $panelSubmissionMailto */
    /** @var string|null $panelSubmissionEmail */
@endphp
<button type="button" class="cen-publico__invite-link" id="cen-invite-panel-open" aria-haspopup="dialog"
    aria-controls="cen-invite-panel-dialog">
    ¿Quieres publicar un panel?
</button>

<dialog id="cen-invite-panel-dialog" class="cen-publico__invite-dialog" aria-labelledby="cen-invite-panel-title">
    <div class="cen-publico__invite-dialog-inner">
        <header class="cen-publico__invite-dialog-header">
            <h2 id="cen-invite-panel-title">Publicar un panel en Centinela</h2>
            <button type="button" class="cen-publico__invite-close" id="cen-invite-panel-close"
                aria-label="Cerrar">×</button>
        </header>

        <p class="cen-publico__empty" style="margin:0 0 0.75rem;">
            Las propuestas se revisan periódicamente por el administrador de la plataforma.
        </p>

        <p style="margin:0 0 0.35rem;font-weight:600;color:#1a3a5c;font-size:14px;">Cómo enviar tu propuesta</p>
        <ol class="cen-publico__invite-list">
            <li>Revisa los requisitos de más abajo.</li>
            <li>Escribe al administrador con el nombre del panel, institución, contacto y el entregable (archivo o
                enlace).</li>
            <li>Tras la revisión, si cumple los requisitos, el panel podrá publicarse en el dashboard.</li>
        </ol>

        <div class="cen-publico__invite-admin" style="margin:0 0 1.25rem;">
            <p style="margin:0 0 0.25rem;font-weight:600;color:#1a3a5c;font-size:14px;">Administrador de la plataforma
            </p>
            @if ($panelSubmissionMailto)
                <p class="cen-publico__empty" style="margin:0;font-size:14px;">
                    Correo:
                    <a href="{{ $panelSubmissionMailto }}">{{ $panelSubmissionEmail }}</a>
                </p>
            @else
                <p class="cen-publico__empty" style="margin:0;" role="status">
                    Contacto no disponible por ahora. Intenta más tarde o consulta a la administración de la plataforma.
                </p>
            @endif
        </div>

        <p style="margin:0 0 0.35rem;font-weight:600;color:#1a3a5c;font-size:14px;">Requisitos para publicar un panel
        </p>
        <ul class="cen-publico__invite-list">
            <li><strong>Actualización periódica:</strong>
                el autor puede enviar actualizaciones o usar paneles de actualización automática que la plataforma
                permita. Los paneles desactualizados podrían bajarse previo aviso del administrador.</li>
            <li><strong>Protección de datos:</strong>
                sin datos personales identificables; solo agregados públicos.</li>
            <li><strong>Alcance nacional:</strong>
                datos a nivel país, con desagregación por Servicio de Salud y/o establecimiento.</li>
            <li><strong>Formatos técnicos:</strong>
                HTML, CSV y PNG (vista previa); el HTML se revisa por seguridad. También se aceptan enlaces públicos
                allowlist (p. ej. Power BI, Tableau, Looker Studio, Datawrapper, Flourish, ArcGIS).</li>
            <li><strong>Fuente y metodología:</strong>
                origen de los datos y método de cálculo o agregación comprensibles para el público general.</li>
            <li><strong>Seguridad del HTML:</strong>
                sin depender de la sesión de Centinela ni romper el aislamiento del iframe.</li>
            <li><strong>Responsable:</strong>
                el panel debe indicar nombre, institución y contacto.</li>
            <li><strong>Autorización:</strong>
                quien envía el panel acepta que Centinela pueda republicarlo (uso institucional).</li>
            <li><strong>¿Trabajas con agentes de IA?</strong>
                <a href="{{ route('centinela.publico.contrato-agentes') }}">Descarga el contrato</a>
                (formato ZIP, seguridad y requisitos de entrega).
            </li>
        </ul>
    </div>
</dialog>

<style>
    .cen-publico__invite-heading {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem 1.25rem;
        flex-wrap: wrap;
    }

    .cen-publico__invite-link {
        background: none;
        border: none;
        padding: 0;
        margin-left: auto;
        color: #1a3a5c;
        text-decoration: underline;
        cursor: pointer;
        font: inherit;
        font-size: 14px;
        white-space: nowrap;
    }

    .cen-publico__invite-link:hover,
    .cen-publico__invite-link:focus-visible {
        color: #0d2a45;
    }

    .cen-publico__invite-dialog {
        border: none;
        border-radius: 10px;
        padding: 0;
        max-width: min(720px, calc(100vw - 2rem));
        width: 100%;
        max-height: min(90vh, 880px);
        box-shadow: 0 12px 40px rgba(26, 58, 92, 0.28);
    }

    .cen-publico__invite-dialog::backdrop {
        background: rgba(26, 58, 92, 0.45);
    }

    .cen-publico__invite-dialog-inner {
        padding: 1.35rem 1.6rem 1.5rem;
        background: #fff;
        max-height: inherit;
        overflow-y: auto;
        box-sizing: border-box;
    }

    .cen-publico__invite-dialog-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .cen-publico__invite-dialog-header h2 {
        margin: 0;
        font-size: 1.15rem;
        color: #1a3a5c;
    }

    .cen-publico__invite-close {
        border: none;
        background: transparent;
        font-size: 1.5rem;
        line-height: 1;
        color: #6c757d;
        cursor: pointer;
        padding: 0 0.15rem;
    }

    .cen-publico__invite-list {
        margin: 0 0 1rem;
        padding-left: 1.2rem;
        font-size: 14px;
        line-height: 1.45;
        color: #333;
    }

    .cen-publico__invite-list li {
        margin-bottom: 0.4rem;
    }

    .cen-publico__invite-admin a {
        color: #1a3a5c;
        font-weight: 600;
        word-break: break-all;
    }
</style>

<script>
    (function() {
        var dialog = document.getElementById('cen-invite-panel-dialog');
        var openBtn = document.getElementById('cen-invite-panel-open');
        var closeBtn = document.getElementById('cen-invite-panel-close');
        if (!dialog || !openBtn) {
            return;
        }
        openBtn.addEventListener('click', function() {
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                dialog.close();
            });
        }
        dialog.addEventListener('click', function(event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    })();
</script>
