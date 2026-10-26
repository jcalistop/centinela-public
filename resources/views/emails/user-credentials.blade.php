<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Credenciales de acceso</title>
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

        .credentials {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #006DB0;
        }

        .credentials dt {
            font-weight: bold;
            margin-top: 8px;
        }

        .credentials dd {
            margin: 4px 0 0 0;
            font-family: Consolas, 'Courier New', monospace;
        }

        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                padding: 10px !important;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        @include('emails.partials.centinela-header')
        <div class="content" role="main">
            <p>Estimad@ {{ $user->name }}:</p>
            <p>
                @if ($isUpdate)
                    Se ha actualizado tu acceso a la plataforma {{ $appName }}.
                @else
                    Se ha creado tu acceso a la plataforma {{ $appName }}.
                @endif
            </p>
            <p>Utiliza las siguientes credenciales para ingresar:</p>

            <dl class="credentials">
                <dt>Usuario</dt>
                <dd>{{ $user->email }}</dd>
                <dt>Contraseña</dt>
                <dd>{{ $plainPassword }}</dd>
            </dl>

            <div class="info">
                <p style="margin-bottom: 0;"><strong>Importante:</strong> Por seguridad, cambie su contraseña después
                    del
                    primer ingreso. No reenvíe este correo ni comparta estas credenciales.</p>
            </div>
        </div>

        <div class="button-container">
            <a href="{{ $loginUrl }}" class="button" role="button" aria-label="Ingresar a Centinela">Ingresar a
                Centinela</a>
        </div>

        <p>Si el botón no funciona, copie y pegue este enlace en su navegador:</p>
        <p style="word-break: break-all;"><small>{{ $loginUrl }}</small></p>

        <p>Atte.</p>
        <p>Soporte Plataforma Centinela</p>
    </div>
    <div class="footer" role="contentinfo">
        <p>Este es un correo automático. Por favor no responda a este mensaje.</p>
        <p>&copy; {{ date('Y') }} Plataforma Centinela</p>
    </div>
</body>

</html>
