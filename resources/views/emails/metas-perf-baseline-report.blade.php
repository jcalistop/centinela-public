<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Baseline de rendimiento — Metas</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
        }

        .header {
            background-color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 10px 20px 20px;
            background-color: #ffffff;
        }

        h2 {
            color: #006DB0;
            font-size: 20px;
            margin: 0 0 12px;
        }

        h3 {
            color: #004085;
            font-size: 16px;
            margin: 24px 0 10px;
            border-bottom: 1px solid #eeeeee;
            padding-bottom: 6px;
        }

        p {
            margin-bottom: 14px;
            font-size: 15px;
        }

        .lead {
            color: #555555;
            font-size: 15px;
        }

        .info {
            background-color: #e9f5fb;
            padding: 14px 16px;
            border-radius: 4px;
            margin: 16px 0;
            color: #004085;
            border-left: 4px solid #006DB0;
        }

        .warn {
            background-color: #fff3cd;
            padding: 14px 16px;
            border-radius: 4px;
            margin: 16px 0;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .context-box {
            background-color: #f8f9fa;
            padding: 14px 16px;
            border-radius: 4px;
            margin: 16px 0;
            border-left: 4px solid #006DB0;
            font-size: 14px;
        }

        .context-box dt {
            font-weight: bold;
            margin-top: 8px;
        }

        .context-box dd {
            margin: 2px 0 0 0;
            font-family: Consolas, 'Courier New', monospace;
            color: #444444;
        }

        .metrics-table {
            width: 100%;
            margin: 12px 0 8px;
            border-collapse: collapse;
        }

        .metrics-table th {
            background-color: #006DB0;
            color: #ffffff;
            padding: 10px 8px;
            text-align: left;
            font-size: 13px;
        }

        .metrics-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
            font-size: 13px;
            vertical-align: top;
        }

        .metrics-table tr:nth-child(even) td {
            background-color: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-codigo {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-capacidad {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-inconcluyente {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .legend {
            background-color: #f8f9fa;
            padding: 14px 16px;
            border-radius: 4px;
            margin: 12px 0 20px;
            font-size: 13px;
            color: #555555;
        }

        .legend ul {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .legend li {
            margin-bottom: 6px;
        }

        .path {
            word-break: break-all;
            font-family: Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: #444444;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            padding: 20px;
            border-top: 1px solid #eeeeee;
        }

        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                padding: 10px !important;
            }
        }
    </style>
</head>

<body>
    @php
        $scenarioLabels = [
            'catalog' => 'Catálogo de indicadores',
            'hub' => 'Hub de establecimientos',
            'results_summary' => 'Resumen de resultados',
        ];
        $findingLabels = [
            'codigo' => 'Código / modelo',
            'capacidad' => 'Capacidad / entorno',
            'inconcluyente' => 'Inconcluyente',
        ];
        $ctx = $report['context'] ?? [];
        $isPartial = !empty($report['partial']);
    @endphp

    <div class="container">
        @include('emails.partials.centinela-header')

        <div class="content" role="main">
            <h2>Baseline de rendimiento — módulo Metas</h2>

            <p class="lead">
                Este correo resume una corrida de medición de rendimiento del módulo Metas,
                ejecutada desde Voyager Compass. El objetivo es dejar una <strong>línea base</strong>
                comparable para evaluar mejoras futuras y orientar si un cuello de botella parece
                de <em>código</em> o de <em>capacidad del servidor</em> (este último fuera del
                alcance de fixes de producto).
            </p>

            @if ($isPartial)
                <div class="warn" role="status">
                    <strong>Corrida parcial.</strong>
                    Se alcanzó el límite de tiempo (<code>--max-seconds</code>) antes de completar
                    todos los escenarios.
                    @if (!empty($report['skipped']))
                        Omitidos:
                        <code>{{ implode(', ', $report['skipped']) }}</code>.
                    @endif
                    Los datos siguientes cubren solo lo medido.
                </div>
            @endif

            <div class="context-box">
                <strong>Contexto de la medición</strong>
                <dl>
                    <dt>Inicio / fin</dt>
                    <dd>{{ $report['started_at'] ?? '—' }} → {{ $report['finished_at'] ?? '—' }}</dd>
                    <dt>Usuario actor (user_id)</dt>
                    <dd>{{ $ctx['user_id'] ?? '—' }}</dd>
                    <dt>Servicio de Salud (cen_hie_dependency_id)</dt>
                    <dd>{{ $ctx['cen_hie_dependency_id'] ?? '—' }}</dd>
                    <dt>Año indicador</dt>
                    <dd>{{ $ctx['year_ano'] ?? '—' }} (year_id {{ $ctx['year_id'] ?? '—' }})</dd>
                    <dt>Instrumento (indicator_type_id)</dt>
                    <dd>{{ $ctx['indicator_type_id'] ?? '—' }}</dd>
                    <dt>Corridas por escenario / modo</dt>
                    <dd>
                        {{ $ctx['runs'] ?? '—' }}
                        ·
                        {{ !empty($ctx['warm']) ? 'warm (caché de app posible)' : 'cold (primera pasada del escenario)' }}
                    </dd>
                    <dt>Entorno APP_ENV</dt>
                    <dd>{{ $ctx['app_env'] ?? '—' }}</dd>
                </dl>
            </div>

            <h3>Resultados por escenario</h3>
            <p class="lead" style="margin-bottom: 8px;">
                Cada escenario monta el componente Livewire equivalente al flujo que ve el usuario
                en Metas (sin navegador). Las métricas promedian las corridas exitosas.
            </p>

            <table class="metrics-table" role="table">
                <thead>
                    <tr>
                        <th>Escenario</th>
                        <th>Runs OK</th>
                        <th>Consultas SQL<br><small>(promedio)</small></th>
                        <th>Tiempo<br><small>(ms, promedio)</small></th>
                        <th>Memoria<br><small>(MB, máx.)</small></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['scenarios'] ?? [] as $scenario)
                        @php
                            $agg = $scenario['aggregates'] ?? [];
                            $name = $scenario['name'] ?? '—';
                            $label = $scenarioLabels[$name] ?? $name;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $label }}</strong><br>
                                <small style="color:#6c757d;"><code>{{ $name }}</code></small>
                            </td>
                            <td>{{ $agg['ok_runs'] ?? 0 }}</td>
                            <td>{{ $agg['avg_query_count'] ?? '—' }}</td>
                            <td>{{ $agg['avg_wall_ms'] ?? '—' }}</td>
                            <td>{{ $agg['max_peak_memory_mb'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Sin escenarios medidos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="legend">
                <strong>Leyenda de métricas</strong>
                <ul>
                    <li><strong>Runs OK:</strong> cuántas repeticiones del escenario terminaron sin error
                        (de las configuradas en la corrida).</li>
                    <li><strong>Consultas SQL (promedio):</strong> cantidad media de queries a la base de datos
                        por corrida. Valores altos (p. ej. cientos) suelen indicar patrones N+1 o trabajo
                        repetido en el hot-path → hallazgo tipificado como <em>código / modelo</em>.</li>
                    <li><strong>Tiempo (ms):</strong> tiempo de pared (wall clock) en milisegundos desde el inicio
                        hasta el fin del montaje/render del escenario. No es TTFB de navegador; mide el costo
                        de servidor del componente.</li>
                    <li><strong>Memoria (MB):</strong> pico de memoria PHP observado durante la corrida
                        (referencia operativa; no sustituye métricas del host IIS/MySQL).</li>
                    <li><strong>Catálogo:</strong> listado operacional de indicadores
                        (<code>IndicatorCatalogComponent</code>).</li>
                    <li><strong>Hub:</strong> selección / inclusión de establecimientos
                        (<code>CenterSelectionComponent</code>).</li>
                    <li><strong>Resumen de resultados:</strong> vista de resultados agregados
                        (<code>ResultsSummaryComponent</code>).</li>
                </ul>
            </div>

            <h3>Hallazgos clasificados</h3>
            <p class="lead">
                La clasificación es orientativa (heurística automática). Un humano debe validarla antes
                de abrir un spec de mejora o de atribuir el problema solo al servidor.
            </p>

            <ul style="padding-left: 18px;">
                @forelse ($report['findings'] ?? [] as $finding)
                    @php
                        $raw = $finding['label'] ?? 'inconcluyente';
                        $badgeClass = match ($raw) {
                            'codigo' => 'badge-codigo',
                            'capacidad' => 'badge-capacidad',
                            default => 'badge-inconcluyente',
                        };
                        $labelEs = $findingLabels[$raw] ?? $raw;
                        $scn = $finding['scenario'] ?? null;
                        $scnLabel = $scn ? $scenarioLabels[$scn] ?? $scn : null;
                    @endphp
                    <li style="margin-bottom: 12px;">
                        <span class="badge {{ $badgeClass }}">{{ $labelEs }}</span>
                        @if ($scnLabel)
                            <span style="color:#6c757d;">· {{ $scnLabel }}</span>
                        @endif
                        <br>
                        {{ $finding['note'] ?? '' }}
                    </li>
                @empty
                    <li>Sin hallazgos generados.</li>
                @endforelse
            </ul>

            <div class="legend">
                <strong>Leyenda de hallazgos</strong>
                <ul>
                    <li><span class="badge badge-codigo">Código / modelo</span>
                        Candidato a mejora en la aplicación (queries, N+1, batch incompleto, etc.).
                        Puede motivar un spec hijo de rendimiento en dominio Metas.</li>
                    <li><span class="badge badge-capacidad">Capacidad / entorno</span>
                        Sospecha de límites del servidor o del runtime (memoria baja, I/O lento con pocas
                        queries, etc.). <strong>No</strong> implica un ticket de optimización de código;
                        documentar y escalar a infraestructura si corresponde.</li>
                    <li><span class="badge badge-inconcluyente">Inconcluyente</span>
                        Baseline registrado, pero sin señal clara entre código y capacidad.
                        Sirve para comparar con corridas futuras o requiere otra pasada.</li>
                </ul>
            </div>

            <h3>Archivos en el servidor</h3>
            <div class="info">
                <p style="margin: 0 0 8px;">
                    La fuente de verdad queda en <code>storage</code> del servidor (no se hace commit desde
                    producción). Si el adjunto Markdown no llegó (tamaño o política de correo), use estas rutas:
                </p>
                <p class="path" style="margin: 0 0 6px;">
                    MD: {{ $report['markdown_path'] ?? '—' }}
                </p>
                <p class="path" style="margin: 0;">
                    JSON: {{ $report['json_path'] ?? '—' }}
                </p>
            </div>

            <p>Atte.<br>Plataforma Centinela — medición operativa (SDD-182)</p>
        </div>
    </div>

    <div class="footer" role="contentinfo">
        <p>Este es un correo automático. Por favor no responda a este mensaje.</p>
        <p>&copy; {{ date('Y') }} Plataforma Centinela</p>
    </div>
</body>

</html>
