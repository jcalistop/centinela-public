@props([
    'indicatorModule',
    'indicatorId',
    'informacionTabLabel' => 'Información',
    'informacionTabTitle' => null,
    'informacionTabDescription' => null,
    'aplicablesConvenidosTabLabel' => 'Indicadores aplicables y en convenio',
    'aplicablesConvenidosTabTitle' => null,
    'aplicablesConvenidosTabDescription' => null,
    'desempenoTabLabel' => 'Desempeño por establecimiento',
    'desempenoTabTitle' => null,
    'desempenoTabDescription' => null,
    'bitacoraTabTitle' => null,
    'bitacoraTabDescription' => null,
])

@php
    use App\Enums\Indicators\IndicatorModuleType;
    use App\Models\IndicatorActivity;

    $module = IndicatorModuleType::from($indicatorModule);
    $isMetasModule = $module === IndicatorModuleType::Metas;
    $activityCount = IndicatorActivity::query()->forIndicator($module, (int) $indicatorId)->count();
    $hasAplicablesConvenidosTab = isset($aplicablesConvenidos);
    $hasDesempenoTab = isset($desempeno);
@endphp

<ul @class(['nav', 'nav-tabs', 'metas-nav-tabs' => $isMetasModule]) role="tablist" @unless ($isMetasModule) style="margin-bottom:0" @endunless>
    <li role="presentation" class="active">
        <a href="#tab-informacion" role="tab" data-toggle="tab">
            <i class="voyager-info-circled"></i> {{ $informacionTabLabel }}
        </a>
    </li>
    @if ($hasAplicablesConvenidosTab)
        <li role="presentation">
            <a href="#tab-aplicables-convenidos" role="tab" data-toggle="tab">
                <i class="voyager-company"></i> {{ $aplicablesConvenidosTabLabel }}
            </a>
        </li>
    @endif
    @if ($hasDesempenoTab)
        <li role="presentation">
            <a href="#tab-desempeno" role="tab" data-toggle="tab">
                <i class="voyager-bar-chart"></i> {{ $desempenoTabLabel }}
            </a>
        </li>
    @endif
    <li role="presentation">
        <a href="#tab-bitacora" role="tab" data-toggle="tab">
            <i class="voyager-logbook"></i> Bitácora{{ $activityCount > 0 ? ' (' . $activityCount . ')' : '' }}
        </a>
    </li>
</ul>

<div @class(['tab-content', 'metas-tab-content' => $isMetasModule]) @unless ($isMetasModule) style="padding-top:20px" @endunless>
    <div role="tabpanel" class="tab-pane active" id="tab-informacion">
        @if ($informacionTabTitle)
            <x-indicators.detail-tab-header :title="$informacionTabTitle" :description="$informacionTabDescription" :variant="$isMetasModule ? 'ficha-section' : 'spotlight'">
                @isset($informacionActions)
                    <x-slot:actions>{{ $informacionActions }}</x-slot:actions>
                @endisset
            </x-indicators.detail-tab-header>
        @endif
        {{ $informacion ?? $slot }}
    </div>
    @if ($hasAplicablesConvenidosTab)
        <div role="tabpanel" class="tab-pane" id="tab-aplicables-convenidos">
            @if ($aplicablesConvenidosTabTitle)
                <x-indicators.detail-tab-header :title="$aplicablesConvenidosTabTitle" :description="$aplicablesConvenidosTabDescription" />
            @endif
            {{ $aplicablesConvenidos }}
        </div>
    @endif
    @if ($hasDesempenoTab)
        <div role="tabpanel" class="tab-pane" id="tab-desempeno">
            @if ($desempenoTabTitle)
                <x-indicators.detail-tab-header :title="$desempenoTabTitle" :description="$desempenoTabDescription" />
            @endif
            {{ $desempeno }}
        </div>
    @endif
    <div role="tabpanel" class="tab-pane" id="tab-bitacora">
        @if ($bitacoraTabTitle)
            <x-indicators.detail-tab-header :title="$bitacoraTabTitle" :description="$bitacoraTabDescription" />
        @endif
        @livewire(
            'indicator-timeline-component',
            [
                'indicatorType' => $indicatorModule,
                'indicatorId' => (int) $indicatorId,
            ],
            key('indicator-timeline-' . $indicatorModule . '-' . $indicatorId)
        )
    </div>
</div>
