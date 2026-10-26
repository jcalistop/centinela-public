@php
    $compact = $compact ?? false;
    $imageMargin = $compact ? '2rem auto 1rem auto' : '5rem auto 5rem auto';
@endphp

<img src="{{ asset('images/screens.svg') }}"
    style="margin: {{ $imageMargin }}; width: 30%; max-width: 32rem; display: block;">
<div class="text-center" @if ($compact) style="margin-bottom: 1.5rem;" @endif>
    <h4 class="text-bold">Bienvenido a Centinela</h4>
    <p>La plataforma de visualización de indicadores para los Servicios de Salud.</p>
</div>
