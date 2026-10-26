<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
        }

        .header {
            background-color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 10px;
            background-color: #ffffff;
            border-radius: 4px;
        }

        .content p {
            margin-bottom: 16px;
            font-size: 16px;
        }

        .content ul,
        .content ol {
            margin: 0 0 16px 1.25rem;
            font-size: 16px;
        }

        .message-body {
            margin: 8px 0 20px;
        }

        .details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #006DB0;
        }

        .details dt {
            font-weight: bold;
            margin-top: 8px;
            color: #004085;
        }

        .details dt:first-child {
            margin-top: 0;
        }

        .details dd {
            margin: 4px 0 0 0;
        }

        .info {
            background-color: #e9f5fb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            color: #004085;
            font-size: 14px;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            padding: 20px;
            border-top: 1px solid #eeeeee;
        }
    </style>
</head>

<body>
    <div class="container">
        @include('emails.partials.centinela-header')
        <div class="content" role="main">
            <div class="message-body">
                {!! str($bodyHtml)->sanitizeHtml() !!}
            </div>

            <dl class="details">
                <dt>Enviado por</dt>
                <dd>{{ $senderName }}</dd>
                <dt>Correo</dt>
                <dd>{{ $senderEmail }}</dd>
                <dt>Unidad</dt>
                <dd>{{ $senderUnidad }}</dd>
                <dt>Servicio de Salud</dt>
                <dd>{{ $senderServicioSalud }}</dd>
            </dl>

            <div class="info">
                <p style="margin-bottom: 0;">
                    Puede responder a este mensaje: la respuesta llegará al correo del emisor
                    (<strong>{{ $senderEmail }}</strong>).
                </p>
            </div>
        </div>
    </div>
    <div class="footer" role="contentinfo">
        <p>Este es un correo de la plataforma Centinela. El remitente técnico es no-reply; use «Responder» para
            contactar al emisor.</p>
        <p>&copy; {{ date('Y') }} Plataforma Centinela</p>
    </div>
</body>

</html>
