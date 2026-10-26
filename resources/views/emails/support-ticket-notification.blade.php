<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Notificación Centinela</title>
    <style>
        /* ---------- reset / base ---------- */
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

        /* ---------- header ---------- */
        .header {
            background: linear-gradient(135deg, #006DB0 0%, #004b7a 100%);
            padding: 28px 32px;
            color: #fff;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .header h1 span {
            opacity: 0.75;
            font-weight: 400;
        }

        .header .subhead {
            margin: 6px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        /* ---------- action banner ---------- */
        .action-banner {
            background: #eef5fb;
            border-left: 4px solid #006DB0;
            padding: 14px 20px;
            margin: 0 0 20px 0;
            font-size: 15px;
            color: #1a3c5e;
            border-radius: 0 4px 4px 0;
        }

        .action-banner .action-icon {
            font-size: 20px;
            margin-right: 8px;
        }

        .action-banner strong {
            font-weight: 600;
        }

        /* ---------- content ---------- */
        .content {
            padding: 28px 32px;
            background-color: #ffffff;
        }

        .subject-line {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 4px 0;
            line-height: 1.4;
        }

        .ticket-id {
            font-size: 13px;
            color: #888;
            margin: 0 0 18px 0;
        }

        /* ---------- disclaimer ---------- */
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

        /* ---------- status badge ---------- */
        .status-badge {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.02em;
        }

        .status-badge--open {
            background-color: #3498db;
        }

        .status-badge--in_progress {
            background-color: #f39c12;
        }

        .status-badge--resolved {
            background-color: #27ae60;
        }

        .status-badge--closed {
            background-color: #566573;
        }

        /* ---------- info table ---------- */
        .info-table {
            width: 100%;
            margin: 18px 0;
            border-collapse: collapse;
            font-size: 14px;
            border: 1px solid #e8e8e8;
            border-radius: 4px;
            overflow: hidden;
        }

        .info-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
        }

        .info-table tr:last-child td {
            border-bottom: 0;
        }

        .info-table td:first-child {
            font-weight: 600;
            color: #495057;
            width: 32%;
            white-space: nowrap;
            background: #fafbfc;
        }

        .info-table td:last-child {
            color: #222;
        }

        /* ---------- description box ---------- */
        .desc-label {
            font-size: 13px;
            font-weight: 700;
            color: #495057;
            margin: 22px 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .body-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 14px 18px;
            margin: 0 0 4px 0;
            white-space: pre-wrap;
            font-size: 14px;
            color: #333;
            line-height: 1.65;
        }

        /* ---------- comment highlight ---------- */
        .comment-box {
            background: #fffbe6;
            border: 1px solid #f7e8b0;
            border-radius: 4px;
            padding: 12px 16px;
            margin: 8px 0 14px 0;
            font-size: 13px;
            color: #5a4a1a;
        }

        .comment-box strong {
            color: #8b7a2e;
        }

        /* ---------- CTA ---------- */
        .cta-wrap {
            margin: 28px 0 0 0;
            text-align: center;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #22a7f0 0%, #1a8fc8 100%);
            color: #ffffff !important;
            padding: 13px 34px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(34, 167, 240, 0.3);
        }

        .cta-button:hover {
            background: linear-gradient(135deg, #1a8fc8 0%, #1578a8 100%);
        }

        .cta-url {
            font-size: 12px;
            word-break: break-all;
            color: #aaa;
            margin-top: 10px;
        }

        .cta-url a {
            color: #888;
            text-decoration: none;
        }

        .cta-url a:hover {
            text-decoration: underline;
        }

        /* ---------- footer ---------- */
        .footer {
            text-align: center;
            padding: 24px 20px;
            font-size: 12px;
            color: #999;
            background: #fafbfc;
            border-top: 1px solid #e8e8e8;
        }

        .footer strong {
            color: #666;
        }

        .footer p {
            margin: 4px 0;
        }

        /* ---------- responsive ---------- */
        @media screen and (max-width: 480px) {
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
    <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" style="height:auto;v-text-anchor:middle;" arcsize="6" strokecolor="#006DB0" fillcolor="#006DB0"><w:anchorlock/><center style="color:#ffffff;font-family:sans-serif;font-size:22px;font-weight:700;">&nbsp;<![endif]-->
    <div class="container">
        <div class="header">
            <h1>Plataforma Centinela</h1>
            <p class="subhead">{{ $emailSubjectLine }}</p>
        </div>

        <div class="content">
            <div class="action-banner">
                <span class="action-icon">{!! $actionIcon !!}</span>
                <strong>{{ $actionText }}</strong>
            </div>

            @if (!empty($comment))
                <div class="comment-box">
                    <strong>
                        &#128172;
                        Comentario
                        @if (!empty($commentAuthorName))
                            de {{ $commentAuthorName }}
                        @endif
                        :
                    </strong>
                    {{ strip_tags($comment) }}
                </div>
            @endif

            <p class="ticket-id">Información del ticket #{{ $ticket->id }}</p>
            <p class="subject-line">{{ \Illuminate\Support\Str::limit(strip_tags($ticket->subject), 200) }}</p>

            <table class="info-table" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td>Estado</td>
                    <td>
                        <span
                            class="status-badge status-badge--{{ $ticket->status instanceof \BackedEnum ? $ticket->status->value : $ticket->status }}">
                            {{ $ticket->status instanceof \App\Enums\SupportTicketStatus ? $ticket->status->label() : ($ticket->status instanceof \BackedEnum ? $ticket->status->value : $ticket->status) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>Área</td>
                    <td>{{ \App\Support\SupportTicket\SupportTicketCatalog::labelForArea($ticket->area) }}</td>
                </tr>
                @if (is_array($ticket->attachment) && !empty($ticket->attachment['name']))
                    <tr>
                        <td>Archivo adjunto</td>
                        <td>{{ $ticket->attachment['name'] }}</td>
                    </tr>
                @endif
                <tr>
                    <td>Servicio de Salud</td>
                    <td>{{ $ticket->cenHieDependency?->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Creado por</td>
                    <td>{{ $ticket->createdBy?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Asignado a</td>
                    <td>{{ $ticket->assignedTo?->name ?? 'No asignado' }}</td>
                </tr>
                @if ($ticket->indicatorType)
                    <tr>
                        <td>Instrumento</td>
                        <td>{{ $ticket->indicatorType->nombre_indicador }}</td>
                    </tr>
                @endif
                @if ($ticket->year)
                    <tr>
                        <td>Año</td>
                        <td>{{ $ticket->year->ano }}</td>
                    </tr>
                @endif
            </table>

            <div class="desc-label">Descripción</div>
            <div class="body-box">{{ \Illuminate\Support\Str::limit(strip_tags($ticket->body), 800) }}</div>

            <div class="disclaimer">
                <strong>&#9432; Solo notificación automática</strong> &mdash; no responda a este correo.
                Para responder o ver el detalle, ingrese a Centinela usando el botón de abajo.
            </div>

            <div class="cta-wrap">
                <a class="cta-button" href="{{ $ticketUrl }}"
                    style="color:#ffffff !important;text-decoration:none;">Ver ticket en Centinela &rarr;</a>
                <div class="cta-url">
                    <a href="{{ $ticketUrl }}">{{ $ticketUrl }}</a>
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
