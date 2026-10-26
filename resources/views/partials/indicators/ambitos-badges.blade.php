@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\IndicatorAmbito>|\App\Models\IndicatorAmbito[]|list<array{nombre: string, slug: string}>|null $ambitos */
    use App\Models\User;
    use App\Support\Dashboard\NonAdminDashboardPreview;

    $ambitos = $ambitos ?? collect();
    $linkToDetail = (bool) ($linkToDetail ?? false) && NonAdminDashboardPreview::ambitoDetailLinksEnabled();
    $showLabel = (bool) ($showLabel ?? false);
    $authUser = auth()->user();
@endphp
@if ($ambitos->isNotEmpty())
    <div class="indicator-ambitos-badges metas-catalog-ambitos" style="margin-top: 0.25rem;">
        @if ($showLabel)
            <span class="text-muted small metas-catalog-ambitos__label">Ámbitos:</span>
        @endif
        @foreach ($ambitos as $ambito)
            @php
                $ambitoNombre = is_array($ambito) ? (string) ($ambito['nombre'] ?? '') : (string) $ambito->nombre;
                $ambitoSlug = is_array($ambito) ? (string) ($ambito['slug'] ?? '') : (string) $ambito->slug;
            @endphp
            @if ($linkToDetail && $authUser instanceof User && filled($ambitoSlug))
                <a href="{{ NonAdminDashboardPreview::ambitoDetailUrl($authUser, $ambitoSlug) }}"
                    class="label label-info metas-ambito-label indicator-ambito-badge-link"
                    title="Ver indicadores del ámbito {{ $ambitoNombre }}">
                    {{ $ambitoNombre }}
                </a>
            @else
                <span class="label label-info metas-ambito-label">{{ $ambitoNombre }}</span>
            @endif
        @endforeach
    </div>
@else
    <div style="margin-top: 0.25rem;">
        <span class="text-muted small">Sin ámbito</span>
    </div>
@endif
