<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Respaldo de Base de Datos</title>
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
            background-color: {{ $isProduction ? '#28a745' : '#17a2b8' }};
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .backup-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
            background-color: #ffffff;
        }

        .success-box {
            background-color: {{ $isProduction ? '#d4edda' : '#d1ecf1' }};
            border-left: 4px solid {{ $isProduction ? '#28a745' : '#17a2b8' }};
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .success-box h2 {
            color: {{ $isProduction ? '#155724' : '#0c5460' }};
            margin-top: 0;
            font-size: 18px;
        }

        .success-box p {
            color: {{ $isProduction ? '#155724' : '#0c5460' }};
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

        .info-table tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-production {
            background-color: #28a745;
            color: #ffffff;
        }

        .badge-local {
            background-color: #17a2b8;
            color: #ffffff;
        }

        .badge-staging {
            background-color: #ffc107;
            color: #000000;
        }

        .footer {
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .footer p {
            margin: 5px 0;
        }

        code {
            background-color: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #e83e8c;
        }

        .note {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .note p {
            color: #856404;
            margin: 5px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="backup-icon">💾</div>
            <h1>Respaldo de Base de Datos</h1>
            <p style="margin: 5px 0; font-size: 14px;">Sistema Centinela - MINSAL</p>
        </div>

        <div class="content">
            <div class="success-box">
                <h2>✅ Respaldo Completado Exitosamente</h2>
                <p>El respaldo automático de la base de datos se ha generado correctamente.</p>
            </div>

            <table class="info-table">
                <tr>
                    <td>Ambiente:</td>
                    <td>
                        @if ($isProduction)
                            <span class="badge badge-production">🔴 Producción</span>
                        @elseif($environment === 'staging')
                            <span class="badge badge-staging">🟡 Staging</span>
                        @else
                            <span class="badge badge-local">🔵 Local / Desarrollo</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Fecha y Hora:</td>
                    <td>{{ $timestamp }}</td>
                </tr>
                <tr>
                    <td>Base de Datos:</td>
                    <td><code>{{ $database }}</code></td>
                </tr>
                <tr>
                    <td>Archivo:</td>
                    <td><code>{{ $fileName }}</code></td>
                </tr>
                <tr>
                    <td>Tamaño:</td>
                    <td><strong>{{ $fileSize }}</strong></td>
                </tr>
                <tr>
                    <td>Servidor:</td>
                    <td><code>{{ $hostname }}</code></td>
                </tr>
                @if ($isProduction)
                    <tr>
                        <td>Retención:</td>
                        <td>{{ $retentionDays }} días</td>
                    </tr>
                @endif
            </table>

            @if (!$isProduction)
                <div class="note">
                    <p><strong>ℹ️ Nota:</strong> Este respaldo se generó en un ambiente de desarrollo/prueba. Los
                        respaldos de producción tienen configuración y retención diferentes.</p>
                </div>
            @endif

            @if ($isProduction)
                <div style="margin-top: 30px;">
                    <h3 style="color: #006DB0;">📋 Información Adicional</h3>
                    <ul style="color: #495057; line-height: 1.8;">
                        <li>Los respaldos locales se conservan durante <strong>{{ $retentionDays }} días</strong></li>
                        <li>Se recomienda almacenar respaldos críticos en ubicación externa</li>
                        <li>Este archivo está comprimido en formato ZIP para optimizar el envío</li>
                        <li>Verificar la integridad del archivo adjunto antes de eliminar respaldos locales</li>
                    </ul>
                </div>
            @endif
        </div>

        <div class="footer">
            <p><strong>Sistema Centinela - MINSAL</strong></p>
            <p>Servicio de Salud Del Reloncaví</p>
            <p style="font-size: 12px; color: #999;">Este es un correo automático del sistema de respaldos. No
                responder.</p>
        </div>
    </div>
</body>

</html>
