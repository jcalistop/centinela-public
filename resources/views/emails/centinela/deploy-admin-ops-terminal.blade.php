<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Deploy Centinela — ops</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 6px;
            overflow: hidden;
        }

        .header {
            background-color: #006DB0;
            background: linear-gradient(135deg, #006DB0 0%, #004b7a 100%);
            padding: 28px 32px;
            color: #ffffff;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: #ffffff;
        }

        .header .subhead {
            margin: 6px 0 0 0;
            font-size: 14px;
            color: #ffffff;
            opacity: 0.95;
        }

        .content {
            padding: 28px 32px;
            background-color: #ffffff;
        }

        .intro {
            font-size: 15px;
            margin: 0 0 18px;
        }

        .action-banner {
            background: #eef5fb;
            border-left: 4px solid #006DB0;
            padding: 14px 20px;
            margin: 0 0 22px 0;
            font-size: 15px;
            color: #1a3c5e;
            border-radius: 0 4px 4px 0;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 22px 0;
            font-size: 14px;
        }

        .info-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eef0f3;
            vertical-align: top;
        }

        .info-table td:first-child {
            width: 34%;
            color: #666;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.02em;
        }

        .status-badge--succeeded {
            background: #28a745;
        }

        .status-badge--skipped {
            background: #6c757d;
        }

        .status-badge--failed {
            background: #dc3545;
        }

        .status-badge--failed_precondition {
            background: #fd7e14;
        }

        .summary-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 12px 14px;
            font-size: 14px;
            color: #333;
            margin: 0 0 22px 0;
            white-space: pre-wrap;
        }

        .disclaimer {
            font-size: 13px;
            color: #777;
            background: #f8f9fa;
            padding: 10px 14px;
            border-radius: 4px;
            margin: 0 0 22px 0;
            border: 1px solid #e9ecef;
        }

        .disclaimer strong {
            color: #555;
        }

        .cta-wrap {
            margin: 8px 0 0;
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

        .cta-url {
            margin-top: 10px;
            font-size: 12px;
            word-break: break-all;
        }

        .cta-url a {
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

        .footer p {
            margin: 4px 0;
        }

        @media only screen and (max-width: 620px) {
            .content {
                padding: 20px 18px;
            }

            .header {
                padding: 20px 18px;
            }

            .header h1 {
                font-size: 18px;
            }

            .info-table td:first-child {
                width: 38%;
            }

            .cta-button {
                display: block;
            }
        }
    </style>
</head>

<body>
    @php
        $statusClass = match ($status) {
            'succeeded' => 'status-badge--succeeded',
            'skipped' => 'status-badge--skipped',
            'failed_precondition' => 'status-badge--failed_precondition',
            default => 'status-badge--failed',
        };
        $statusLabel = match ($status) {
            'succeeded' => 'Exitoso',
            'skipped' => 'Omitido',
            'failed_precondition' => 'Precondición fallida',
            'failed' => 'Fallido',
            default => $status,
        };
        $bannerText = match ($status) {
            'succeeded' => 'Deploy cerrado con éxito',
            'skipped' => 'Deploy omitido (idempotente)',
            'failed_precondition' => 'Deploy no iniciado (precondición)',
            default => 'Deploy cerrado con fallo',
        };
    @endphp
    <div class="container"
        style="max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:6px;overflow:hidden;">
        <div class="header" bgcolor="#006DB0"
            style="background-color:#006DB0;background:linear-gradient(135deg,#006DB0 0%,#004b7a 100%);padding:28px 32px;color:#ffffff;">
            <h1 style="margin:0;font-size:22px;font-weight:700;letter-spacing:0.01em;color:#ffffff !important;">
                Plataforma Centinela</h1>
            <p class="subhead" style="margin:6px 0 0 0;font-size:14px;color:#ffffff !important;opacity:0.95;">
                Alerta ops — ejecución de deploy #{{ $executionId }}</p>
        </div>

        <div class="content">
            <p class="intro">Hola {{ $recipientName }},</p>

            <div class="action-banner">
                <strong>{{ $bannerText }}</strong>
            </div>

            <table class="info-table" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td>Ejecución</td>
                    <td>#{{ $executionId }}</td>
                </tr>
                <tr>
                    <td>Modo</td>
                    <td>{{ $mode }}</td>
                </tr>
                <tr>
                    <td>Estado</td>
                    <td>
                        <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
            </table>

            <div class="desc-label" style="font-size: 13px; font-weight: 700; color: #555; margin: 0 0 8px;">Resumen
            </div>
            <div class="summary-box">{{ $summary !== '' ? $summary : '—' }}</div>

            <div class="disclaimer">
                <strong>&#9432; Correo ops/admin</strong> &mdash; no sustituye la notificación oficial a
                control-gestión. No responda a este mensaje.
            </div>

            <div class="cta-wrap">
                <a class="cta-button" href="{{ $panelUrl }}"
                    style="color:#ffffff !important;text-decoration:none;">Abrir panel admin &rarr;</a>
                <div class="cta-url">
                    <a href="{{ $panelUrl }}">{{ $panelUrl }}</a>
                </div>
            </div>
        </div>

        <div class="footer">
            <p><strong>Plataforma Centinela</strong></p>
            <p>Ministerio de Salud &bull; Gobierno de Chile</p>
            <p>Este es un correo automático. No responder a este mensaje.</p>
        </div>
    </div>
</body>

</html>
