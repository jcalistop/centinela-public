Estimad@ {{ $user->name }},

Se registró una nueva nota en la bitácora de un indicador en {{ $appName }}.

Módulo: {{ $mailData->moduleLabel }}
Instrumento / compromiso: {{ $mailData->contextLabel }}
Servicio de Salud: {{ $mailData->servicioSaludNombre }}
Año: {{ $mailData->ano }}
Indicador: {{ $mailData->indicatorClave }}: {{ $mailData->indicatorNombre }}

Nota registrada:
{{ $mailData->noteText }}

Registrada por {{ $mailData->authorName }} el {{ $mailData->registeredAt }}.

Ver bitácora del indicador: {{ $mailData->bitacoraUrl }}

---
Este es un correo automático. Por favor no responda a este mensaje.
© {{ date('Y') }} Plataforma Centinela
