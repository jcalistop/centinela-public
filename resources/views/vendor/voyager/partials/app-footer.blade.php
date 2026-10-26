@php
    $canCreateSupportTicket =
        \App\Helpers\ModuleAccess::canAccess('soporte') &&
        auth()->user()?->can('create', \App\Models\SupportTicket::class);
@endphp
<footer class="app-footer">
    <div class="app-footer__card" role="contentinfo">
        <div class="app-footer__support" role="note">
            <span class="app-footer__support-text">
                ¿Dudas o problemas? Utiliza el módulo Soporte.
            </span>
            @if ($canCreateSupportTicket)
                <a class="app-footer__support-cta" href="{{ route('centinela.support.create') }}">Crear ticket</a>
            @endif
        </div>
        <div class="site-footer-right">
            Desarrollado por Departamento de Control de Gestión,
            <a href="https://ssreloncavi.redsalud.gob.cl/" target="_blank" rel="noopener noreferrer">Servicio de Salud
                del Reloncaví</a>.
        </div>
    </div>
</footer>
