<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Restablecer Contraseña</title>
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

        .header svg {
            max-width: 280px;
            height: auto;
        }

        .logo-text {
            color: #006DB0;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            display: none;
        }

        .content {
            padding: 10px;
            background-color: #ffffff;
            border-radius: 4px;
        }

        h1 {
            color: #006DB0;
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
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

        .button:hover {
            background-color: #005690;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            padding: 20px;
            border-top: 1px solid #eeeeee;
        }

        .alert {
            padding: 15px;
            background-color: #f8d7da;
            color: #721c24;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .info {
            background-color: #e9f5fb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            color: #004085;
        }

        /* Mejoras para clientes que no soportan HTML bien */
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
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta para la plataforma
                {{ $appName ?? 'Centinela' }}.</p>
            <p>Para completar el proceso de restablecimiento, haz clic en el siguiente botón:</p>
        </div>

        <div class="button-container">
            <a href="{{ $url }}" class="button" role="button"
                aria-label="Restablecer tu contraseña">Restablecer Contraseña</a>
        </div>

        <p>Si el botón no funciona, puedes copiar y pegar este enlace en tu navegador:</p>
        <p style="word-break: break-all;"><small>{{ $url }}</small></p>

        <p>Este enlace de restablecimiento de contraseña expirará en {{ $count }} minutos.</p>

        <p><strong>Importante:</strong> Si no solicitaste este cambio, puedes ignorar este correo y tu
            contraseña permanecerá igual.</p>

        <p>Por razones de seguridad, este enlace solo puede utilizarse una vez.</p>
        <p>Atte.</p>
        <p>Soporte Plataforma Centinela</p>
    </div>
    <div class="footer" role="contentinfo">
        <p>Este es un correo automático. Por favor no responda a este mensaje.</p>
        <p>&copy; {{ date('Y') }} Plataforma Centinela</p>
    </div>
    </div>
</body>

</html>
