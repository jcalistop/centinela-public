@php
    $moduleEnabled = $nonAdminDashboardModuleEnabled ?? true;
    $previewSsId = $nonAdminDashboardPreviewSsId ?? null;
    $servicios = $nonAdminDashboardPreviewServicios ?? collect();
@endphp

<div class="alert alert-warning" style="margin-bottom: 1rem;">
    <strong><i class="voyager-eye"></i> Vista previa (solo administrador)</strong>
    <p style="margin: 0.5rem 0 0;">
        Muestra el dashboard operativo tal como lo verían usuarios no admin en el año y servicio de salud seleccionados.
        @if (!$moduleEnabled)
            <br><span class="text-danger">El módulo está <strong>deshabilitado</strong> para usuarios operativos; esta
                pestaña permite revisarlo antes de activarlo.</span>
        @endif
    </p>
</div>

@if ($servicios->isNotEmpty())
    <form method="GET" action="{{ route('voyager.dashboard') }}" class="form-inline" style="margin-bottom: 1rem;">
        <input type="hidden" name="tab" value="operativo">
        <label for="dashboard-preview-ss" class="sr-only">Servicio de salud</label>
        <select name="ss" id="dashboard-preview-ss" class="form-control" onchange="this.form.submit()">
            @foreach ($servicios as $ss)
                <option value="{{ $ss->id }}" @selected((int) $previewSsId === (int) $ss->id)>
                    {{ $ss->codigo }} — {{ $ss->nombre }}
                </option>
            @endforeach
        </select>
        <noscript>
            <button type="submit" class="btn btn-default btn-sm">Aplicar</button>
        </noscript>
    </form>
@endif
