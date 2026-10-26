<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Nueva nota en bitácora de indicador</title>
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

        .content {
            padding: 10px;
            background-color: #ffffff;
            border-radius: 4px;
        }

        p {
            margin-bottom: 16px;
            font-size: 16px;
        }

        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        .button {
            display: inline-block;
            background-color: #006DB0;
            color: #ffffff !important;
            font-weight: bold;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-size: 16px;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            padding: 20px;
            border-top: 1px solid #eeeeee;
        }

        .info {
            background-color: #e9f5fb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            color: #004085;
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
        }

        .details dd {
            margin: 4px 0 0 0;
        }

        .note-box {
            background-color: #fffbe6;
            border: 1px solid #f7e8b0;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
            white-space: pre-wrap;
            font-size: 15px;
            color: #333333;
        }

        .note-meta {
            font-size: 14px;
            color: #555555;
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <div class="container">
        @include('emails.partials.centinela-header')
        <div class="content" role="main">
            <p>Estimad@ {{ $user->name }}:</p>
            <p>
                Se registró una nueva nota en la bitácora de un indicador en {{ $appName }}.
            </p>

            <dl class="details">
                <dt>Módulo</dt>
                <dd>{{ $mailData->moduleLabel }}</dd>
                <dt>Instrumento / compromiso</dt>
                <dd>{{ $mailData->contextLabel }}</dd>
                <dt>Servicio de Salud</dt>
                <dd>{{ $mailData->servicioSaludNombre }}</dd>
                <dt>Año</dt>
                <dd>{{ $mailData->ano }}</dd>
                <dt>Indicador</dt>
                <dd>{{ $mailData->indicatorClave }}: {{ $mailData->indicatorNombre }}</dd>
            </dl>

            <p><strong>Nota registrada</strong></p>
            <div class="note-box">{{ $mailData->noteText }}</div>
            <p class="note-meta">
                Registrada por <strong>{{ $mailData->authorName }}</strong> el {{ $mailData->registeredAt }}.
            </p>

            <div class="info">
                <p style="margin-bottom: 0;">Puede revisar la bitácora completa del indicador desde el enlace siguiente.
                </p>
            </div>
        </div>

        <div class="button-container">
            <a href="{{ $mailData->bitacoraUrl }}" class="button" role="button"
                aria-label="Ver bitácora del indicador">Ver bitácora del indicador</a>
        </div>

        <p>Si el botón no funciona, copie y pegue este enlace en su navegador:</p>
        <p style="word-break: break-all;"><small>{{ $mailData->bitacoraUrl }}</small></p>

        <p>Atte.</p>
        <p>Soporte Plataforma Centinela</p>
    </div>
    <div class="footer" role="contentinfo">
        <p>Este es un correo automático. Por favor no responda a este mensaje.</p>
        <p>&copy; {{ date('Y') }} Plataforma Centinela</p>
    </div>
</body>

</html>
