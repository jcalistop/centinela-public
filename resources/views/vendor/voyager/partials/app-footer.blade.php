@php
    $canCreateSupportTicket =
        \App\Helpers\ModuleAccess::canAccess('soporte') &&
        auth()->user()?->can('create', \App\Models\SupportTicket::class);
    $instanceUnit = \App\Support\Configuration\InstanceIdentity::unitName();
    $instanceName = \App\Support\Configuration\InstanceIdentity::displayName();
    $instanceUrl = \App\Support\Configuration\InstanceIdentity::url();
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
            {{ $instanceUnit }},
            @if ($instanceUrl !== '')
                <a href="{{ $instanceUrl }}" target="_blank" rel="noopener noreferrer">{{ $instanceName }}</a>.
            @else
                {{ $instanceName }}.
            @endif
        </div>
    </div>
</footer>
