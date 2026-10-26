@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
@stop

@section('content')
    <div class="page-content compass container-fluid">
        @include('voyager::alerts')
        @include('voyager::dimmers')

        @php
            if (Session::get('indicator_year') == null) {
                Session::flush();
            }

            $adminUsageTabUrl = function (string $tab) use ($nonAdminDashboardPreviewSsId): string {
                if ($tab === 'operativo') {
                    return \App\Support\Dashboard\NonAdminDashboardPreview::operativoTabUrl(
                        !empty($nonAdminDashboardPreviewSsId) ? (int) $nonAdminDashboardPreviewSsId : null,
                    );
                }

                return route('voyager.dashboard') . '?' . http_build_query(['tab' => $tab]);
            };
        @endphp

        {{-- MENSAJE EN MANTENIMIENTO --}}
        @if (setting('admin.modo_mantenimiento') == 'On')
            <div class="text-center">
                <img src="{{ asset('images/mantenimiento.svg') }}" style="margin: 5rem 0 5rem 0; max-width: 35rem;">
                <h4 class="text-bold">Sitio en Mantenimiento</h4>
                <p>
                    Lamentamos las molestias, pero estamos realizando tareas de mantenimiento.<br>
                    Agradecemos salir del sistema en estos momentos.
                </p>
                <form action="{{ route('voyager.logout') }}" method="POST">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-lg btn-danger">
                        <i class="voyager-forward"></i>
                        Cerrar sesión
                    </button>
                </form>
            </div>
        @else
            <div class="container">
                @if ($showNonAdminDashboard ?? false)
                    <livewire:dashboard.non-admin-dashboard-panel />
                @elseif (!$canSeeUsageDashboard)
                    @include('vendor.voyager.partials.dashboard-welcome')
                @endif
            </div>

            @if (!empty($showAdminUsagePanel))
                <div class="admin-dashboard-compass admin-dashboard-compass--usage cen-compass-ui">
                    <div class="cen-dashboard-shell">
                        <x-centinela.compass.dashboard-block title="Dashboard para administradores"
                            icon="voyager-analytics">
                            <ul class="nav nav-tabs" role="tablist">
                                <li role="presentation" class="{{ ($activeUsageTab ?? 'uso') === 'uso' ? 'active' : '' }}">
                                    <a href="{{ $adminUsageTabUrl('uso') }}" role="tab">
                                        <i class="voyager-activity"></i> Uso del sistema
                                    </a>
                                </li>
                                <li role="presentation" class="{{ ($activeUsageTab ?? '') === 'tablas' ? 'active' : '' }}">
                                    <a href="{{ $adminUsageTabUrl('tablas') }}" role="tab">
                                        <i class="voyager-list"></i> Tablas
                                    </a>
                                </li>
                                <li role="presentation" class="{{ ($activeUsageTab ?? '') === 'salud' ? 'active' : '' }}">
                                    <a href="{{ $adminUsageTabUrl('salud') }}" role="tab">
                                        <i class="voyager-warning"></i> Salud del sistema
                                    </a>
                                </li>
                                <li role="presentation"
                                    class="{{ ($activeUsageTab ?? '') === 'operativo' ? 'active' : '' }}">
                                    <a href="{{ $adminUsageTabUrl('operativo') }}" role="tab">
                                        <i class="voyager-people"></i> Vista operativa
                                        <span class="label label-warning" style="margin-left: 4px;">Preview</span>
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">
                                @if (($activeUsageTab ?? 'uso') === 'operativo')
                                    <div role="tabpanel" class="tab-pane active" id="tab-operativo">
                                        @include('vendor.voyager.partials.dashboard-preview-controls')
                                        @if (!empty($nonAdminDashboardPreviewSsId))
                                            <livewire:dashboard.non-admin-dashboard-panel :preview-mode="true"
                                                :preview-ss-id="$nonAdminDashboardPreviewSsId" />
                                        @else
                                            <p class="text-muted">Seleccione año de indicadores y un servicio de salud para
                                                la vista previa.</p>
                                        @endif
                                    </div>
                                @elseif (($activeUsageTab ?? 'uso') === 'uso')
                                    <div role="tabpanel" class="tab-pane active" id="tab-uso">
                                        @include('voyager::partials.admin-usage-tab-uso')
                                    </div>
                                @elseif (($activeUsageTab ?? '') === 'tablas')
                                    <div role="tabpanel" class="tab-pane active" id="tab-tablas">
                                        @include('voyager::partials.admin-usage-tab-tablas', [
                                            'usageDashboard' => $usageDashboard ?? [],
                                        ])
                                    </div>
                                @elseif (($activeUsageTab ?? '') === 'salud' && is_array($systemHealth ?? null))
                                    <div role="tabpanel" class="tab-pane active" id="tab-salud">
                                        @include('voyager::partials.admin-usage-tab-salud', [
                                            'systemHealth' => $systemHealth,
                                            'retentionOps' => $retentionOps ?? [],
                                        ])
                                    </div>
                                @endif
                            </div>
                        </x-centinela.compass.dashboard-block>
                    </div>
                </div>
            @endif
        @endif

        @include('voyager::partials.git-deploy-panel')
    </div>
@stop

@section('javascript')
    @if (
        ($usageEnabled ?? false) &&
            ($canSeeUsageDashboard ?? false) &&
            ($activeUsageTab ?? 'uso') === 'uso' &&
            is_array($usageDashboard ?? null))
        @include('voyager::partials.admin-usage-charts-script')
    @endif
@stop
