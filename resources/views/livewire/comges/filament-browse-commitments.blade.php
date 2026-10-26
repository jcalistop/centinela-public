@php
    $fmtScore = static function (?float $value): string {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 2, ',', '.');
    };

    $scoreColor = static function (?float $value) use ($useScoreScale): string {
        if ($value === null) {
            return 'gray';
        }
        if ($useScoreScale) {
            if ($value >= 3.0) {
                return 'success';
            }
            if ($value >= 1.0) {
                return 'warning';
            }

            return 'danger';
        }
        if ($value >= 75) {
            return 'success';
        }
        if ($value >= 50) {
            return 'warning';
        }

        return 'danger';
    };
@endphp

<div wire:key="filament-browse-year-{{ $yearId ?? 'none' }}" class="space-y-4">
    @livewire(
        \App\Filament\ServicioSalud\Widgets\ComgesBrowseStatsOverview::class,
        [
            'commitmentsCount' => $kpiCommitments,
            'alertsCount' => $kpiAlerts,
            'corteLabel' => $kpiCorte,
            'verificadorCount' => $kpiVerificador,
        ],
        key('comges-stats-' . $yearId . '-' . $kpiCommitments . '-' . $kpiAlerts . '-' . $kpiVerificador)
    )

    @unless ($useScoreScale)
        <x-filament::section>
            <x-slot name="heading">Escala legacy</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Año {{ $yearAno }} · cumplimiento en %. El layout score del mockup aplica desde 2025.
            </p>
        </x-filament::section>
    @endunless

    <x-filament::tabs label="Secciones COMGES">
        <x-filament::tabs.item :active="true">
            Compromisos
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="false">
            Seguimiento del corte
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="false">
            Mis indicadores
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="false">
            Resultados
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="false">
            Documentos
        </x-filament::tabs.item>
    </x-filament::tabs>

    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-64 flex-1">
                <label for="filament-comges-search"
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Buscar
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input id="filament-comges-search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Compromiso, indicador, clave…" />
                </x-filament::input.wrapper>
            </div>
            <div class="w-56">
                <label for="filament-comges-ambito"
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Ámbito
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="filament-comges-ambito" wire:model.live="ambitoFilter">
                        <option value="">Todos</option>
                        <option value="none">Sin ámbito</option>
                        @foreach ($indicatorAmbitoOptions as $ambito)
                            <option value="{{ $ambito->id }}">{{ $ambito->nombre }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <x-filament::button color="gray" wire:click="clearFilters">
                Limpiar
            </x-filament::button>
        </div>
    </x-filament::section>

    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
        <span class="font-semibold text-gray-700 dark:text-gray-200">Leyenda</span>
        @if ($useScoreScale)
            <x-filament::badge color="success">≥ 3,00 ptos</x-filament::badge>
            <x-filament::badge color="warning">1,00–2,99</x-filament::badge>
            <x-filament::badge color="danger">&lt; 1,00</x-filament::badge>
        @else
            <x-filament::badge color="success">≥ 75%</x-filament::badge>
            <x-filament::badge color="warning">50–74%</x-filament::badge>
            <x-filament::badge color="danger">&lt; 50%</x-filament::badge>
        @endif
        <x-filament::badge color="gray">Sin dato</x-filament::badge>
    </div>

    @forelse ($commitments as $commitment)
        @php
            $headAlerts = collect($alertGroups)
                ->flatten(1)
                ->filter(function ($alert) use ($commitment) {
                    return str_starts_with($alert->anchorKey, 'commitment:' . $commitment->id) ||
                        $commitment->comIndicators->contains(
                            fn($indicator) => str_contains($alert->anchorKey, (string) $indicator->id),
                        );
                })
                ->unique('id')
                ->values();
        @endphp

        <x-filament::section wire:key="filament-commitment-{{ $commitment->id }}" collapsible collapsed="false" compact>
            <x-slot name="heading">
                {{ $commitment->clave }} · {{ $commitment->nombre }}
            </x-slot>
            <x-slot name="description">
                {{ $commitment->comIndicators->count() }}
                indicador{{ $commitment->comIndicators->count() !== 1 ? 'es' : '' }}
                @if ($useScoreScale && $kpiCorte !== '—')
                    · corte vigente {{ $kpiCorte }}
                @endif
            </x-slot>
            <x-slot name="afterHeader">
                <div class="flex flex-wrap gap-2">
                    @forelse ($headAlerts->take(4) as $alert)
                        <x-filament::badge :color="match ($alert->severity) {
                            \App\Support\Alerts\VisualAlertSeverity::Warning => str_contains(
                                strtolower($alert->title),
                                'verificador',
                            )
                                ? 'danger'
                                : 'warning',
                            \App\Support\Alerts\VisualAlertSeverity::Success => 'success',
                            default => 'gray',
                        }">
                            {{ $alert->title }}
                        </x-filament::badge>
                    @empty
                        @if ($useScoreScale && !$isFiltering)
                            <x-filament::badge color="success">Al día</x-filament::badge>
                        @endif
                    @endforelse
                </div>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10">
                            <th class="px-3 py-2">Indicador</th>
                            <th class="px-3 py-2">{{ $useScoreScale ? 'Componente' : 'Detalle' }}</th>
                            @if ($useScoreScale)
                                <th class="px-3 py-2 text-center">Auto</th>
                                <th class="px-3 py-2 text-center">Prelim.</th>
                                <th class="px-3 py-2 text-center">Final</th>
                            @else
                                <th class="px-3 py-2 text-center">Cumpl.</th>
                            @endif
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($commitment->comIndicators as $comIndicator)
                            @php
                                $auto = $useScoreScale
                                    ? $comIndicator->puntajePonderado('AUTOEVALUACION')
                                    : (is_numeric($comIndicator->promedioCumplimiento ?? null)
                                        ? (float) $comIndicator->promedioCumplimiento
                                        : $comIndicator->puntajePonderado('AUTOEVALUACION'));
                                $prelim = $useScoreScale ? $comIndicator->puntajePonderado('PRELIMINAR') : null;
                                $final = $useScoreScale ? $comIndicator->puntajePonderado('FINAL') : null;
                                $componente =
                                    $comIndicator->comIndicatorCutoffs->first()?->cutoff?->nombre_corte ?? '—';
                                $rowAlerts = collect($alertGroups)
                                    ->flatten(1)
                                    ->filter(fn($alert) => str_contains($alert->anchorKey, (string) $comIndicator->id))
                                    ->take(2);
                            @endphp
                            <tr wire:key="fi-ind-{{ $comIndicator->id }}"
                                class="border-b border-gray-100 dark:border-white/5">
                                <td class="px-3 py-3 align-top">
                                    <div class="font-medium text-gray-950 dark:text-white">
                                        {{ $comIndicator->clave }}
                                        @if ($comIndicator->nombre)
                                            {{ $comIndicator->nombre }}
                                        @endif
                                    </div>
                                    @foreach ($rowAlerts as $alert)
                                        <div class="mt-1 text-xs text-gray-500">
                                            <x-filament::badge size="sm" :color="($alert->severity->value ?? '') === 'danger' ? 'danger' : 'warning'">
                                                {{ $alert->title }}
                                            </x-filament::badge>
                                        </div>
                                    @endforeach
                                </td>
                                <td class="px-3 py-3 align-top text-gray-600 dark:text-gray-300">
                                    {{ $componente }}
                                </td>
                                @if ($useScoreScale)
                                    <td class="px-3 py-3 text-center align-top">
                                        <x-filament::badge :color="$scoreColor($auto)">
                                            {{ $fmtScore($auto) }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-3 py-3 text-center align-top">
                                        <x-filament::badge :color="$scoreColor($prelim)">
                                            {{ $fmtScore($prelim) }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-3 py-3 text-center align-top">
                                        <x-filament::badge :color="$scoreColor($final)">
                                            {{ $fmtScore($final) }}
                                        </x-filament::badge>
                                    </td>
                                @else
                                    <td class="px-3 py-3 text-center align-top">
                                        <x-filament::badge :color="$scoreColor(is_numeric($auto) ? (float) $auto : null)">
                                            {{ is_numeric($auto) ? number_format((float) $auto, 0) . '%' : '—' }}
                                        </x-filament::badge>
                                    </td>
                                @endif
                                <td class="px-3 py-3 text-right align-top">
                                    <x-filament::button tag="a" size="sm" color="warning"
                                        :href="route('voyager.indicadores-comges.show', $comIndicator->id)">
                                        Ver
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                    Sin indicadores en este compromiso.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($useScoreScale && !$isFiltering && $commitment->comIndicators->isNotEmpty())
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-white/5">
                                <td class="px-3 py-3 font-semibold text-gray-950 dark:text-white" colspan="2">
                                    Desempeño compromiso
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <x-filament::badge :color="$scoreColor($commitment->puntajePonderado('AUTOEVALUACION'))">
                                        {{ $fmtScore($commitment->puntajePonderado('AUTOEVALUACION')) }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <x-filament::badge :color="$scoreColor($commitment->puntajePonderado('PRELIMINAR'))">
                                        {{ $fmtScore($commitment->puntajePonderado('PRELIMINAR')) }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <x-filament::badge :color="$scoreColor($commitment->puntajePonderado('FINAL'))">
                                        {{ $fmtScore($commitment->puntajePonderado('FINAL')) }}
                                    </x-filament::badge>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            @if ($isFiltering)
                <x-slot name="heading">Sin resultados</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Prueba limpiar filtros o cambiar el criterio de búsqueda.
                </p>
            @elseif ($showsEmptyCommitments)
                <x-slot name="heading">No hay compromisos para el año activo</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Puedes crearlos desde Voyager mientras dura el dual-run.
                </p>
            @else
                <x-slot name="heading">No hay compromisos visibles</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Si corresponde, solicita acceso COMGES a tu SS.
                </p>
            @endif
        </x-filament::section>
    @endforelse
</div>
