Hola {{ $user->name }},

@if ($isUpdate)
    Se ha actualizado tu acceso a la plataforma {{ $appName }}.
@else
    Se ha creado tu acceso a la plataforma {{ $appName }}.
@endif

Credenciales de acceso:
Usuario: {{ $user->email }}
Contraseña: {{ $plainPassword }}

Ingresa en: {{ $loginUrl }}

Importante: Por seguridad, cambie su contraseña después del primer ingreso. No reenvíe este correo ni comparta estas
credenciales.

---
Este es un correo automático. Por favor no responda a este mensaje.
© {{ date('Y') }} Plataforma Centinela
