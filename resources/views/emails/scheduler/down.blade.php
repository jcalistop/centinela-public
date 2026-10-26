<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Scheduler Caído - Alerta</title>
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
            background-color: #dc3545;
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .alert-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
            background-color: #ffffff;
        }

        .alert-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .alert-box h2 {
            color: #721c24;
            margin-top: 0;
            font-size: 18px;
        }

        .alert-box p {
            color: #721c24;
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

        .actions {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .actions h3 {
            color: #856404;
            margin-top: 0;
            font-size: 16px;
        }

        .actions ul {
            color: #856404;
            margin: 10px 0;
            padding-left: 20px;
        }

        .actions li {
            margin: 5px 0;
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
            <div class="alert-icon">🚨</div>
            <h1>ALERTA: Scheduler de Laravel Caído</h1>
        </div>

        <div class="content">
            <div class="alert-box">
                <h2>El scheduler no ha ejecutado correctamente</h2>
                <p>El sistema de monitoreo ha detectado que el scheduler de Laravel no ha registrado un heartbeat en los
                    últimos <strong>{{ $minutesAgo }} minutos</strong>.</p>
            </div>

            <h3 style="color: #006DB0; margin-top: 30px;">Información del Último Heartbeat</h3>
            <table class="info-table">
                <tr>
                    <td>Última ejecución:</td>
                    <td>{{ \Carbon\Carbon::parse($heartbeatData['last_run'])->format('d/m/Y H:i:s') }}</td>
                </tr>
                <tr>
                    <td>Minutos transcurridos:</td>
                    <td><strong>{{ $minutesAgo }} minutos</strong></td>
                </tr>
                <tr>
                    <td>Servidor:</td>
                    <td>{{ $heartbeatData['hostname'] ?? 'Desconocido' }}</td>
                </tr>
                <tr>
                    <td>Ambiente:</td>
                    <td>{{ strtoupper($heartbeatData['environment'] ?? 'unknown') }}</td>
                </tr>
                <tr>
                    <td>PHP Version:</td>
                    <td>{{ $heartbeatData['php_version'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Laravel Version:</td>
                    <td>{{ $heartbeatData['laravel_version'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Zona horaria:</td>
                    <td>{{ $heartbeatData['timezone'] ?? config('app.timezone') }}</td>
                </tr>
            </table>

            <div class="actions">
                <h3>⚠️ Acciones Requeridas</h3>
                <ul>
                    <li>Verificar que el cron esté configurado y ejecutándose correctamente</li>
                    <li>Revisar los logs del sistema y scheduler en <code>storage/logs/laravel-YYYY-MM-DD.log</code>
                    </li>
                    <li>Comprobar el estado del servidor y recursos disponibles</li>
                    <li>Ejecutar manualmente <code>php artisan schedule:run</code> para verificar errores</li>
                    <li>Si usa un servicio externo de cron, verificar su configuración</li>
                </ul>
            </div>

            <h3 style="color: #006DB0; margin-top: 30px;">Comandos Programados</h3>
            <table class="info-table">
                @if (isset($heartbeatData['commands_executed']) && is_array($heartbeatData['commands_executed']))
                    @foreach ($heartbeatData['commands_executed'] as $command)
                        <tr>
                            <td colspan="2">• {{ $command }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="2">No hay información disponible de comandos</td>
                    </tr>
                @endif
            </table>

            <div class="timestamp">
                Alerta generada el {{ now()->format('d/m/Y H:i:s') }}
            </div>
        </div>

        <div class="footer">
            <p><strong>Sistema Centinela - MINSAL</strong></p>
            <p>Este es un correo automático del sistema de monitoreo. No responder a este mensaje.</p>
        </div>
    </div>
</body>

</html>
