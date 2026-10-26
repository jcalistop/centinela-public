<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Solicitud de contacto — Centinela</title>
</head>

<body style="font-family: 'Open Sans', Arial, sans-serif; font-size: 14px; color: #333;">
    <h2 style="margin-bottom: 0.5rem;">Solicitud desde «¿Qué es Centinela?»</h2>
    <p style="margin-top: 0;">Se ha recibido un nuevo mensaje a través del formulario de contacto del panel
        administrativo.</p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr>
            <td><strong>Nombre</strong></td>
            <td>{{ $payload['nombre'] }}</td>
        </tr>
        <tr>
            <td><strong>Servicio de Salud</strong></td>
            <td>{{ $payload['servicio_salud'] }}</td>
        </tr>
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
        @if ($submitter)
            <tr>
                <td><strong>Usuario en sesión</strong></td>
                <td>{{ $submitter->name }} &lt;{{ $submitter->email }}&gt;</td>
            </tr>
        @endif
    </table>

    <h3 style="margin-top: 1.5rem;">Mensaje</h3>
    <pre
        style="white-space: pre-wrap; background: #f7f7f9; padding: 12px; border: 1px solid #e1e1e8; border-radius: 4px;">{{ $payload['mensaje'] }}</pre>
</body>

</html>
