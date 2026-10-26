@php
    $centinelaEmailLogoSrc = isset($message) ? \App\Support\Mail\CentinelaEmailLogo::embedSrc($message) : null;
@endphp
<div class="header">
    @if ($centinelaEmailLogoSrc)
        <img src="{{ $centinelaEmailLogoSrc }}" alt="Sistema Centinela - Ministerio de Salud de Chile"
            style="max-width: 280px; height: auto; display: inline-block;">
    @else
        <h2 style="color: #006DB0; font-size: 24px; margin: 0;">Plataforma Centinela</h2>
    @endif
</div>
