<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Nueva versión Centinela</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
        }

        .container {
            max-width: 640px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 6px;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #006DB0 0%, #004b7a 100%);
            padding: 24px 28px;
            color: #fff;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }

        .header p {
            margin: 6px 0 0;
            font-size: 14px;
            opacity: 0.92;
        }

        .content {
            padding: 28px 32px;
        }

        .intro {
            font-size: 15px;
            margin: 0 0 20px;
        }

        .version-badge {
            display: inline-block;
            background: #eef5fb;
            color: #004b7a;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 15px;
        }

        .cta-wrap {
            margin: 24px 0;
            text-align: center;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #22a7f0 0%, #1a8fc8 100%);
            color: #ffffff !important;
            padding: 12px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 700;
        }

        .release-notes {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e8e8e8;
            font-size: 14px;
            color: #333;
            line-height: 1.65;
        }

        .release-notes h1,
        .release-notes h2,
        .release-notes h3,
        .release-notes h4 {
            color: #1a3c5e;
            margin: 1.25em 0 0.5em;
            line-height: 1.3;
        }

        .release-notes h2 {
            font-size: 18px;
        }

        .release-notes h3 {
            font-size: 16px;
        }

        .release-notes p {
            margin: 0 0 0.85em;
        }

        .release-notes ul,
        .release-notes ol {
            margin: 0 0 1em;
            padding-left: 1.4em;
        }

        .release-notes li {
            margin-bottom: 0.35em;
        }

        .release-notes table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 1em;
            font-size: 13px;
        }

        .release-notes th,
        .release-notes td {
            border: 1px solid #dee2e6;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }

        .release-notes thead th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .release-notes code {
            background: #f4f4f5;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 0.92em;
        }

        .release-notes pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 12px;
            overflow-x: auto;
            border-radius: 4px;
            font-size: 13px;
        }

        .release-notes a {
            color: #006DB0;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #999;
            background: #fafbfc;
            border-top: 1px solid #e8e8e8;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Plataforma Centinela</h1>
            <p>Nueva versión en producción — Ministerio de Salud</p>
        </div>

        <div class="content">
            <p class="intro">
                Hola {{ $recipientName }},
            </p>
            <p class="intro">
                Le informamos que Centinela tiene una nueva versión desplegada:
                <span class="version-badge">{{ $version }}</span>.
            </p>
            <p class="intro">
                A continuación encontrará el resumen de cambios de esta versión. También puede consultar el historial
                completo en la aplicación.
            </p>

            <div class="cta-wrap">
                <a href="{{ $releasesUrl }}" class="cta-button">Ver versiones liberadas</a>
            </div>

            @if ($bodyHtml !== '')
                <div class="release-notes">
                    {!! $bodyHtml !!}
                </div>
            @endif
        </div>

        <div class="footer">
            <p><strong>Plataforma Centinela</strong> — notificación automática</p>
            <p>Este correo fue generado por el sistema; no responda a esta dirección.</p>
        </div>
    </div>
</body>

</html>
