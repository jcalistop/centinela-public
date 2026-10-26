Hola {{ $user->name }},

Ha sido designad@ como referente del siguiente indicador en {{ $appName }}.

Módulo: {{ $mailData->moduleLabel }}
Instrumento / compromiso: {{ $mailData->contextLabel }}
Servicio de Salud: {{ $mailData->servicioSaludNombre }}
Año: {{ $mailData->ano }}
Indicador: {{ $mailData->indicatorClave }}: {{ $mailData->indicatorNombre }}

Ver ficha del indicador: {{ $mailData->showUrl }}
@if ($mailData->cargaUrl)
    Ir a carga de datos: {{ $mailData->cargaUrl }}
@endif

---
Este es un correo automático. Por favor no responda a este mensaje.
© {{ date('Y') }} Plataforma Centinela
