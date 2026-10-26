@php
    $errorCount = is_array($errors) ? count($errors) : 0;
@endphp

<p>Se completó la importación masiva de resultados COMGES.</p>

<ul>
    <li><strong>Tipo de evaluación:</strong> {{ $tipoEvaluacion }}</li>
    <li><strong>Filas procesadas:</strong> {{ $processed }}</li>
    <li><strong>Filas exitosas:</strong> {{ $successful }}</li>
    <li><strong>Filas con error:</strong> {{ $errorCount }}</li>
</ul>

@if ($errorCount > 0)
    <p><strong>Detalle de errores:</strong></p>
    <ul>
        @foreach ($errors as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
