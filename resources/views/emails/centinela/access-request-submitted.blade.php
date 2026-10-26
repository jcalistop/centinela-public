<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Solicitud de acceso — Centinela</title>
</head>

<body style="font-family: 'Open Sans', Arial, sans-serif; font-size: 14px; color: #333;">
    <h2 style="margin-bottom: 0.5rem;">Solicitud de acceso a Centinela</h2>
    <p style="margin-top: 0;">Se ha recibido una solicitud de alta o acceso desde el formulario público del login.</p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr>
            <td><strong>Nombre</strong></td>
            <td>{{ $payload['nombre'] }}</td>
        </tr>
        @if (!empty($payload['cargo']))
            <tr>
                <td><strong>Cargo</strong></td>
                <td>{{ $payload['cargo'] }}</td>
            </tr>
        @endif
        <tr>
            <td><strong>Servicio de salud</strong></td>
            <td>{{ $servicioSaludDisplay }}</td>
        </tr>
        @if ($servicioSalud && !$servicioSalud->habilitado)
            <tr>
                <td><strong>Estado en plataforma</strong></td>
                <td>Servicio de salud no habilitado en Centinela (derivado a soporte central)</td>
            </tr>
        @endif
        <tr>
            <td><strong>Correo</strong></td>
            <td>{{ $payload['email'] }}</td>
        </tr>
        @if (!empty($payload['telefono']))
            <tr>
                <td><strong>Teléfono</strong></td>
                <td>{{ $payload['telefono'] }}</td>
            </tr>
        @endif
    </table>

    @if (!empty($payload['mensaje']))
        <h3 style="margin-top: 1.5rem;">Mensaje</h3>
        <pre style="white-space: pre-wrap; background: #f7f7f9; padding: 12px; border: 1px solid #e1e1e8; border-radius: 4px;">{{ $payload['mensaje'] }}</pre>
    @endif
</body>

</html>
