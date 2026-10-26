@if (!empty($deployedVersion))
    <div class="alert alert-success releases-alert" role="status">
        Versión en producción: <strong>{{ $deployedVersion }}</strong>
        @if (!empty($deployedAt))
            — desplegada el {{ $deployedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
        @endif
    </div>
@else
    <div class="alert alert-warning releases-alert" role="status">
        Sin versión desplegada registrada. Tras un deploy exitoso del orquestador (cola <code>releases</code>) aparecerá
        aquí la versión en producción.
    </div>
@endif
