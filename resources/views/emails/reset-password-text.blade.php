Hola {{ $user->name }},

Recibimos una solicitud para restablecer la contraseña de tu cuenta en el sistema {{ $appName }}.

Para completar el proceso de restablecimiento, visita el siguiente enlace:
{{ $url }}

Este enlace de restablecimiento de contraseña expirará en {{ $count }} minutos.

Si no solicitaste este cambio, puedes ignorar este correo y tu contraseña permanecerá igual.

Por razones de seguridad, este enlace solo puede utilizarse una vez.

---
Este es un correo automático. Por favor no responda a este mensaje.
© {{ date('Y') }} Ministerio de Salud de Chile - Sistema Centinela
