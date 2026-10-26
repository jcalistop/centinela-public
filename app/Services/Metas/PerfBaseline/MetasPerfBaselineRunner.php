<?php

namespace App\Services\Metas\PerfBaseline;

use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Livewire\MetasInstrument\IndicatorCatalogComponent;
use App\Livewire\MetasInstrument\ResultsSummaryComponent;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorCalculationDataScopeService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use App\Services\Metas\MetIndicatorLastLoadedMonthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use RuntimeException;
use Throwable;

class MetasPerfBaselineRunner
{
    public const SCENARIO_CATALOG = 'catalog';

    public const SCENARIO_HUB = 'hub';

    public const SCENARIO_RESULTS = 'results_summary';

    /** @var list<string> */
    public const DEFAULT_SCENARIOS = [
        self::SCENARIO_CATALOG,
        self::SCENARIO_HUB,
        self::SCENARIO_RESULTS,
    ];

    public const REPORT_FILENAME_PREFIX = 'metas-perf-baseline';

    public static function reportBasename(?\DateTimeInterface $at = null): string
    {
        return self::REPORT_FILENAME_PREFIX.'_'.($at ?? now())->format('Y-m-d_Hi');
    }

    public function __construct(
        private readonly MetasPerfBaselineProfiler $profiler,
    ) {}

    /**
     * @param  list<string>  $scenarios
     * @return array{
     *     started_at: string,
     *     finished_at: string|null,
     *     partial: bool,
     *     skipped: list<string>,
     *     environment: array<string, mixed>,
     *     context: array<string, mixed>,
     *     scenarios: list<array<string, mixed>>,
     *     findings: list<array{label: string, scenario: string|null, note: string}>,
     *     markdown_path: string|null,
     *     json_path: string|null
     * }
     */
    public function run(
        User $user,
        Year $year,
        int $ssId,
        array $scenarios,
        int $runs,
        bool $warm,
        ?int $maxSeconds,
        ?string $outputRelative = null,
    ): array {
        $unknown = array_values(array_diff($scenarios, self::DEFAULT_SCENARIOS));
        if ($unknown !== []) {
            throw new RuntimeException('Escenarios desconocidos: '.implode(', ', $unknown));
        }

        Auth::login($user);
        session(['indicator_year' => $year]);

        $indicatorTypeId = (int) (IndicatorType::query()->where('route', 'metas-18834')->value('id')
            ?? IndicatorType::query()->where('route', 'like', 'metas-%')->orderBy('id')->value('id')
            ?? 0);

        if ($indicatorTypeId < 1) {
            throw new RuntimeException('No hay IndicatorType Metas disponible (metas-18834 u otro metas-*).');
        }

        $report = [
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'partial' => false,
            'skipped' => [],
            'environment' => $this->environmentSignals($ssId),
            'context' => [
                'user_id' => $user->id,
                'cen_hie_dependency_id' => $ssId,
                'year_id' => $year->id,
                'year_ano' => $year->ano,
                'indicator_type_id' => $indicatorTypeId,
                'runs' => $runs,
                'warm' => $warm,
                'app_env' => config('app.env'),
            ],
            'scenarios' => [],
            'findings' => [],
            'markdown_path' => null,
            'json_path' => null,
        ];

        $deadline = $maxSeconds !== null ? microtime(true) + $maxSeconds : null;

        foreach ($scenarios as $scenario) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                $report['partial'] = true;
                $report['skipped'][] = $scenario;

                continue;
            }

            $runsPayload = [];
            for ($i = 1; $i <= $runs; $i++) {
                if ($deadline !== null && microtime(true) >= $deadline) {
                    $report['partial'] = true;
                    break;
                }

                if (! $warm && $i === 1) {
                    // Sin flush global destructivo: cold = primera corrida del proceso.
                }

                $this->flushRequestScopedMetasMemos();

                try {
                    $profile = $this->profiler->profile(
                        fn () => $this->executeScenario($scenario, $indicatorTypeId)
                    );
                    unset($profile['result']);
                    $runsPayload[] = array_merge(['run' => $i], $profile);
                } catch (Throwable $e) {
                    Log::warning('metas-perf-baseline escenario falló', [
                        'scenario' => $scenario,
                        'run' => $i,
                        'message' => $e->getMessage(),
                    ]);
                    $runsPayload[] = [
                        'run' => $i,
                        'error' => $e->getMessage(),
                        'wall_ms' => null,
                        'query_count' => null,
                        'peak_memory_mb' => null,
                        'top_tables' => [],
                    ];
                }
            }

            $report['scenarios'][] = [
                'name' => $scenario,
                'runs' => $runsPayload,
                'aggregates' => $this->aggregateRuns($runsPayload),
            ];
        }

        $report['findings'] = $this->classify($report);
        $report['finished_at'] = now()->toIso8601String();

        $paths = $this->persist($report, $outputRelative);
        $report['markdown_path'] = $paths['markdown'];
        $report['json_path'] = $paths['json'];

        return $report;
    }

    private function executeScenario(string $scenario, int $indicatorTypeId): mixed
    {
        return match ($scenario) {
            self::SCENARIO_CATALOG => Livewire::mount(IndicatorCatalogComponent::class, [
                'indicatorTypeId' => $indicatorTypeId,
            ]),
            self::SCENARIO_HUB => Livewire::mount(CenterSelectionComponent::class, [
                'indicatorTypeId' => $indicatorTypeId,
            ]),
            self::SCENARIO_RESULTS => Livewire::mount(ResultsSummaryComponent::class, [
                'indicatorTypeId' => $indicatorTypeId,
            ]),
            default => throw new RuntimeException("Escenario no soportado: {$scenario}"),
        };
    }

    /**
     * Reinicia memos request-scoped entre corridas del sensor (mismo proceso Artisan).
     */
    private function flushRequestScopedMetasMemos(): void
    {
        app()->forgetInstance(MetIndicatorDesempenoByEstablishmentService::class);
        app()->forgetInstance(MetIndicatorLastLoadedMonthService::class);
        app()->forgetInstance(MetIndicatorCalculationDataScopeService::class);
        app(MetFieldSsConfigService::class)->forgetConfigs();
    }

    /**
     * @param  list<array<string, mixed>>  $runs
     * @return array{avg_wall_ms: float|null, avg_query_count: float|null, max_peak_memory_mb: float|null, ok_runs: int}
     */
    private function aggregateRuns(array $runs): array
    {
        $ok = array_values(array_filter($runs, fn (array $r): bool => ! isset($r['error'])));
        if ($ok === []) {
            return [
                'avg_wall_ms' => null,
                'avg_query_count' => null,
                'max_peak_memory_mb' => null,
                'ok_runs' => 0,
            ];
        }

        $walls = array_column($ok, 'wall_ms');
        $queries = array_column($ok, 'query_count');
        $mem = array_column($ok, 'peak_memory_mb');

        return [
            'avg_wall_ms' => round(array_sum($walls) / count($walls), 2),
            'avg_query_count' => round(array_sum($queries) / count($queries), 2),
            'max_peak_memory_mb' => round(max($mem), 2),
            'ok_runs' => count($ok),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array{label: string, scenario: string|null, note: string}>
     */
    private function classify(array $report): array
    {
        $findings = [];
        $env = $report['environment'];

        $memoryLimit = (string) ($env['memory_limit'] ?? '');
        if ($memoryLimit !== '' && $memoryLimit !== '-1' && preg_match('/^(\d+)M$/i', $memoryLimit, $m) === 1 && (int) $m[1] <= 256) {
            $findings[] = [
                'label' => 'capacidad',
                'scenario' => null,
                'note' => "memory_limit={$memoryLimit}: techo bajo puede limitar corridas pesadas (fuera de alcance de fix de código).",
            ];
        }

        foreach ($report['scenarios'] as $scenario) {
            $name = (string) $scenario['name'];
            $agg = $scenario['aggregates'];
            $avgQ = $agg['avg_query_count'];
            $avgW = $agg['avg_wall_ms'];

            if ($agg['ok_runs'] < 1) {
                $findings[] = [
                    'label' => 'inconcluyente',
                    'scenario' => $name,
                    'note' => 'Sin corridas OK; revisar error en runs.',
                ];

                continue;
            }

            if ($avgQ !== null && $avgQ >= 200) {
                $findings[] = [
                    'label' => 'codigo',
                    'scenario' => $name,
                    'note' => "Promedio ~{$avgQ} queries: candidato a N+1 / hot-path (spec hijo performance).",
                ];
            } elseif ($avgW !== null && $avgW >= 3000 && $avgQ !== null && $avgQ < 80) {
                $findings[] = [
                    'label' => 'capacidad',
                    'scenario' => $name,
                    'note' => "Wall ~{$avgW} ms con pocas queries (~{$avgQ}): sospecha de capacidad/I/O host, no de N+1.",
                ];
            } else {
                $findings[] = [
                    'label' => 'inconcluyente',
                    'scenario' => $name,
                    'note' => "Baseline registrado (queries≈{$avgQ}, wall_ms≈{$avgW}); comparar vs corridas futuras.",
                ];
            }
        }

        return $findings;
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentSignals(int $ssId): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_limit' => ini_get('memory_limit'),
            'db_driver' => config('database.default'),
            'db_connection' => config('database.connections.'.config('database.default').'.driver'),
            'met_indicators_count' => MetIndicator::query()->count(),
            'met_configurations_count' => MetIndicatorConfiguration::query()->count(),
            'met_monthly_data_count' => MetMonthlyData::query()->count(),
            'met_results_count' => MetResult::query()->count(),
            'met_data_fields_count' => MetDataField::query()->count(),
            'ss_scope_note' => "Conteos globales de tablas hot (no filtrados por SS {$ssId}).",
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{markdown: string, json: string}
     */
    private function persist(array $report, ?string $outputRelative): array
    {
        $relativeDir = $outputRelative
            ? trim(str_replace('\\', '/', $outputRelative), '/')
            : 'reports/metas-perf-baseline';

        $basename = self::reportBasename();
        $dir = storage_path('app/'.$relativeDir);
        File::ensureDirectoryExists($dir);

        $markdownPath = $dir.DIRECTORY_SEPARATOR.$basename.'.md';
        $jsonPath = $dir.DIRECTORY_SEPARATOR.$basename.'.json';

        File::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
        File::put($markdownPath, $this->toMarkdown($report));

        return [
            'markdown' => $markdownPath,
            'json' => $jsonPath,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function toMarkdown(array $report): string
    {
        $scenarioLabels = [
            self::SCENARIO_CATALOG => 'Catálogo de indicadores',
            self::SCENARIO_HUB => 'Hub de establecimientos',
            self::SCENARIO_RESULTS => 'Resumen de resultados',
        ];
        $findingLabels = [
            'codigo' => 'Código / modelo',
            'capacidad' => 'Capacidad / entorno',
            'inconcluyente' => 'Inconcluyente',
        ];

        $lines = [];
        $lines[] = '# Baseline de rendimiento — módulo Metas';
        $lines[] = '';
        $lines[] = 'Línea base medible (consultas SQL, tiempo de pared, memoria) para comparar mejoras futuras y orientar si el cuello parece de código o de capacidad del servidor.';
        $lines[] = '';
        $lines[] = '- Inicio: '.$report['started_at'];
        $lines[] = '- Fin: '.($report['finished_at'] ?? '—');
        $lines[] = '- Parcial: '.($report['partial'] ? 'sí' : 'no');
        $lines[] = '- user_id: '.$report['context']['user_id'];
        $lines[] = '- cen_hie_dependency_id: '.$report['context']['cen_hie_dependency_id'];
        $lines[] = '- year: '.$report['context']['year_ano'].' (id '.$report['context']['year_id'].')';
        $lines[] = '- APP_ENV: '.$report['context']['app_env'];
        $lines[] = '';
        $lines[] = '## Entorno';
        $lines[] = '';
        foreach ($report['environment'] as $key => $value) {
            $lines[] = '- `'.$key.'`: '.(is_scalar($value) || $value === null ? (string) $value : json_encode($value));
        }
        $lines[] = '';
        $lines[] = '## Escenarios';
        $lines[] = '';
        $lines[] = '| Escenario | Clave | Runs OK | Consultas SQL (avg) | Tiempo ms (avg) | Memoria MB (máx.) |';
        $lines[] = '| --------- | ----- | ------- | ------------------- | --------------- | ----------------- |';
        foreach ($report['scenarios'] as $scenario) {
            $agg = $scenario['aggregates'];
            $name = (string) $scenario['name'];
            $lines[] = sprintf(
                '| %s | `%s` | %s | %s | %s | %s |',
                $scenarioLabels[$name] ?? $name,
                $name,
                $agg['ok_runs'],
                $agg['avg_query_count'] ?? '—',
                $agg['avg_wall_ms'] ?? '—',
                $agg['max_peak_memory_mb'] ?? '—'
            );
        }
        $lines[] = '';
        $lines[] = '### Leyenda de métricas';
        $lines[] = '';
        $lines[] = '- **Runs OK:** repeticiones sin error.';
        $lines[] = '- **Consultas SQL:** promedio de queries; valores altos sugieren N+1 / hot-path de código.';
        $lines[] = '- **Tiempo ms:** wall clock del montaje Livewire (no TTFB de navegador).';
        $lines[] = '- **Memoria MB:** pico PHP durante la corrida.';
        $lines[] = '';
        $lines[] = '## Hallazgos';
        $lines[] = '';
        $lines[] = '| Etiqueta | Escenario | Nota |';
        $lines[] = '| -------- | --------- | ---- |';
        foreach ($report['findings'] as $finding) {
            $label = (string) $finding['label'];
            $scn = $finding['scenario'] ?? null;
            $lines[] = sprintf(
                '| %s | %s | %s |',
                $findingLabels[$label] ?? $label,
                $scn ? ($scenarioLabels[$scn] ?? $scn) : '—',
                str_replace('|', '/', $finding['note'])
            );
        }
        $lines[] = '';
        $lines[] = '### Leyenda de hallazgos';
        $lines[] = '';
        $lines[] = '- **Código / modelo:** candidato a spec de rendimiento en Metas.';
        $lines[] = '- **Capacidad / entorno:** sospecha de servidor/runtime; fuera de alcance de fix de producto.';
        $lines[] = '- **Inconcluyente:** baseline útil para comparar; sin señal clara aún.';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
