@props(['previewMode' => false, 'previewSsId' => null])

<p {{ $attributes->merge(['style' => 'margin-bottom: 1rem;']) }}>
    @if ($previewMode && $previewSsId)
        <a href="{{ \App\Support\Dashboard\NonAdminDashboardPreview::operativoTabUrl($previewSsId) }}"
            class="btn btn-success btn-sm">
            <i class="voyager-angle-left"></i> Volver a la vista operativa
        </a>
    @else
        <a href="{{ url('/admin') }}" class="btn btn-success btn-sm">
            <i class="voyager-angle-left"></i> Volver al inicio
        </a>
    @endif
    @if ($previewMode)
        <span class="label label-warning" style="margin-left: 0.5rem;">Preview admin</span>
    @endif
</p>
