<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Scheduler Recuperado</title>
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
            background-color: #28a745;
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .success-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
            background-color: #ffffff;
        }

        .success-box {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .success-box h2 {
            color: #155724;
            margin-top: 0;
            font-size: 18px;
        }

        .success-box p {
            color: #155724;
            margin: 5px 0;
        }

        .info-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }

        .info-table td:first-child {
            font-weight: bold;
            color: #495057;
            width: 40%;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
        }

        .timestamp {
            font-size: 14px;
            color: #6c757d;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>Scheduler Recuperado</h1>
        </div>

        <div class="content">
            <div class="success-box">
                <h2>El scheduler está operando con normalidad</h2>
                <p>El sistema de monitoreo ha confirmado que el scheduler de Laravel se ha recuperado y está
                    ejecutándose correctamente.</p>
            </div>

            <h3 style="color: #006DB0; margin-top: 30px;">Información de Recuperación</h3>
            <table class="info-table">
                <tr>
                    <td>Recuperado a las:</td>
                    <td><strong>{{ $recoveredAt }}</strong></td>
                </tr>
                <tr>
                    <td>Último heartbeat:</td>
                    <td>hace {{ $minutesAgo }} minutos</td>
                </tr>
                <tr>
                    <td>Estado actual:</td>
                    <td><span style="color: #28a745; font-weight: bold;">✓ Saludable</span></td>
                </tr>
                <tr>
                    <td>Servidor:</td>
                    <td>{{ gethostname() }}</td>
                </tr>
                <tr>
                    <td>Ambiente:</td>
                    <td>{{ strtoupper(config('app.env')) }}</td>
                </tr>
            </table>

            <div
                style="background-color: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p style="color: #0c5460; margin: 0;">
                    <strong>ℹ️ Información:</strong> El scheduler está ahora ejecutando todas las tareas programadas
                    según lo esperado. No se requiere acción adicional.
                </p>
            </div>

            <div class="timestamp">
                Notificación generada el {{ now()->format('d/m/Y H:i:s') }}
            </div>
        </div>

        <div class="footer">
            <p><strong>Sistema Centinela - MINSAL</strong></p>
            <p>Este es un correo automático del sistema de monitoreo. No responder a este mensaje.</p>
        </div>
    </div>
</body>

</html>
