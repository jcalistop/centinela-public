@extends('centinela.publico.layout')

@section('title', ($summary['ss']['nombre'] ?? 'Servicio de Salud') . ' — Centinela')

@section('content')
    <a class="cen-publico__back" href="{{ route('centinela.publico.landing') }}">← Volver al dashboard público</a>

    <div class="cen-publico__section cen-publico__meta">
        <img src="{{ $summary['ss']['logo_url'] }}" alt="{{ $summary['ss']['nombre'] }}">
        <div>
            <h1 style="margin:0 0 0.35rem;font-size:1.35rem;color:#1a3a5c;">{{ $summary['ss']['nombre'] }}</h1>
            <form method="get" action="{{ route('centinela.publico.ss', ['ss' => $summary['ss']['id']]) }}"
                style="display:inline-flex;align-items:center;gap:0.5rem;">
                <label for="year" style="font-size:14px;">Año</label>
                <select name="year" id="year" class="form-control" style="width:auto;display:inline-block;"
                    onchange="this.form.submit()">
                    @foreach ($summary['years'] as $yearOption)
                        <option value="{{ $yearOption['id'] }}" @selected((int) $yearOption['id'] === (int) $summary['year']['id'])>
                            {{ $yearOption['ano'] }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <section class="cen-publico__section">
        <h2>
            COMGES
            @if (!empty($summary['comges_visible']))
                <x-centinela.publico.semaforo :percent="$summary['comges_percent'] ?? null" />
            @endif
        </h2>
        @if (!$summary['comges_visible'])
            <p class="cen-publico__empty">COMGES no está habilitado para este Servicio de Salud en el año seleccionado.</p>
        @elseif (count($summary['comges']) === 0)
            <p class="cen-publico__empty">Sin datos</p>
        @else
            <table class="cen-publico__table">
                <thead>
                    <tr>
                        <th>Compromiso</th>
                        <th>Cumplimiento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['comges'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>
                                <x-centinela.publico.semaforo :percent="$row['percent']" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="cen-publico__metas">
        @if (count($summary['metas']) === 0)
            <div class="cen-publico__section">
                <p class="cen-publico__empty" style="margin:0;">Sin instrumentos Metas habilitados o sin datos.</p>
            </div>
        @else
            <div class="cen-publico__metas-grid">
                @foreach ($summary['metas'] as $instrument)
                    @php
                        $metasSemaforo = \App\Support\Metas\MetSemaforoThresholds::forIndicatorType(
                            (int) $instrument['indicator_type_id'],
                            $instrument['slug'],
                            (int) $summary['year']['id'],
                        );
                    @endphp
                    <article class="cen-publico__section cen-publico__metas-card">
                        <h3>
                            {{ $instrument['label'] }}
                            <x-centinela.publico.semaforo :percent="$instrument['percent']" :green-min="$metasSemaforo->greenMinPercent" :yellow-min="$metasSemaforo->yellowMinPercent" />
                        </h3>
                        @if (count($instrument['establishments']) === 0)
                            <p class="cen-publico__empty">Sin establecimientos con evaluación.</p>
                        @else
                            <table class="cen-publico__table">
                                <thead>
                                    <tr>
                                        <th>Establecimiento</th>
                                        <th>Cumplimiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($instrument['establishments'] as $establishment)
                                        <tr>
                                            <td>{{ $establishment['label'] }}</td>
                                            <td>
                                                <x-centinela.publico.semaforo :percent="$establishment['percent']" :green-min="$metasSemaforo->greenMinPercent"
                                                    :yellow-min="$metasSemaforo->yellowMinPercent" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="cen-publico__section cen-publico__legend" aria-label="Leyenda de semáforos">
        <div class="semaforo-legend">
            <span class="semaforo-legend__item">
                <span class="semaforo semaforo-legend__dot green" aria-hidden="true"></span>
                <span>Cumplimiento total</span>
            </span>
            <span class="semaforo-legend__item">
                <span class="semaforo semaforo-legend__dot orange" aria-hidden="true"></span>
                <span>Cumplimiento parcial</span>
            </span>
            <span class="semaforo-legend__item">
                <span class="semaforo semaforo-legend__dot red" aria-hidden="true"></span>
                <span>Cumplimiento insuficiente</span>
            </span>
            <span class="semaforo-legend__item">
                <span class="semaforo semaforo-legend__dot nulo" aria-hidden="true"></span>
                <span>Sin información</span>
            </span>
        </div>
    </section>
@endsection
