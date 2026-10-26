<?php

namespace App\Services\Dashboard;

use App\Enums\Indicators\IndicatorModuleType;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriodResult;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\IndicatorActivity;
use App\Models\IndicatorAmbito;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetEstablishmentResult;
use App\Models\MetIndicator;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Alerts\CentinelaVisualAlertService;
use App\Services\ComgesResultsService;
use App\Services\Metas\MetasEstablishmentLatestEvaluationService;
use App\Services\Metas\MetDashboardComplianceAggregator;
use App\Services\Metas\MetIndicatorCalculationErrorService;
use App\Services\Metas\MetIndicatorFormulaIssueService;
use App\Services\Metas\MetIndicatorIncompletePeriodService;
use App\Services\Notifications\IndicatorNoteMailDataFactory;
use App\Services\Support\SupportTicketDashboardAlertService;
use App\Support\Alerts\AlertContext;
use App\Support\Alerts\AlertSurface;
use App\Support\Alerts\VisualAlertModule;
use App\Support\Comges\ComgesYearMode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class NonAdminDashboardService
{
    private const CACHE_VERSION = 'v5';

    /**
     * Reglas Metas/tickets evaluadas por `buildAlerts()` además de las visuales COMGES.
     *
     * @var list<string>
     */
    private const METAS_ALERT_RULE_KEYS = [
        'metas_formula',
        'metas_calculo',
        'metas_periodo_incompleto',
        'tickets_soporte',
    ];

    private ?int $previewSsId = null;

    /** @var Collection<int, MetIndicator>|null */
    private ?Collection $cachedMetIndicators = null;

    /** @var array<string, mixed>|null */
    private ?array $cachedMetCompliance = null;

    /** @var Collection<int, ComCommitment>|null */
    private ?Collection $cachedCommitments = null;

    /** @var array<string, bool> */
    private array $instrumentEnabledMemo = [];

    /** @var array<string, array<int, true>> */
    private array $enabledIndicatorTypeIdsByContext = [];

    /**
     * Alertas del dashboard con año/SS explícitos (superficie agentes P3a). No usa sesión.
     *
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    public function alertsFor(User $user, Year $year, int $ssId): array
    {
        if ($ssId <= 0) {
            return [];
        }

        $this->resetRequestCaches();
        $commitments = $this->loadDashboardCommitments($year, $ssId);
        $metIndicators = $this->resolveEnabledMetIndicators($year, $ssId);

        return $this->buildAlerts($user, $commitments, $ssId, $year, $metIndicators);
    }

    /**
     * Frescura de la evaluación de alertas ejecutada en este request (SS superficial, sin historial).
     *
     * `last_evaluated_at` no nulo + `reglas_evaluadas >= 1` = el motor corrió (aunque no haya alertas);
     * ambos nulos/0 = no se evaluó (p. ej. SS inválido).
     *
     * @param  list<array<string, mixed>>|null  $alerts  Resultado ya calculado para este request.
     * @return array{last_evaluated_at: string|null, reglas_evaluadas: int}
     */
    public function alertsEvaluationFor(User $user, Year $year, int $ssId, ?array $alerts = null): array
    {
        if ($ssId <= 0) {
            return ['last_evaluated_at' => null, 'reglas_evaluadas' => 0];
        }

        if ($alerts === null) {
            // Fuerza la evaluación en este request para que el timestamp corresponda a una corrida real.
            $this->alertsFor($user, $year, $ssId);
        }

        return [
            'last_evaluated_at' => now()->toIso8601String(),
            'reglas_evaluadas' => $this->evaluatedAlertRuleCount($year),
        ];
    }

    /**
     * Familias de reglas que `buildAlerts()` invoca en cada evaluación.
     */
    private function evaluatedAlertRuleCount(Year $year): int
    {
        $count = count(self::METAS_ALERT_RULE_KEYS);

        if (ComgesYearMode::usesScoreScale($year)) {
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>|null Null si no aplica el dashboard (admin, sin año, sin SS).
     */
    public function forUser(User $user): ?array
    {
        if ($user->hasRole('admin')) {
            return null;
        }

        $year = session('indicator_year');
        if (! $year instanceof Year) {
            return null;
        }

        if ($user->cen_hie_dependency_id === null) {
            return null;
        }

        $ttl = (int) config('centinela.dashboard_summary.cache_ttl_seconds', 180);
        $cacheKey = sprintf(
            'centinela:non_admin_dashboard:%s:%d:%d:%d',
            self::CACHE_VERSION,
            $user->id,
            $year->id,
            (int) $user->cen_hie_dependency_id
        );

        return Cache::remember($cacheKey, max(30, $ttl), function () use ($user, $year): array {
            return $this->buildPayload($user, $year);
        });
    }

    /**
     * Vista previa admin: mismos widgets que usuarios operativos, sin exigir flag del módulo.
     *
     * @return array<string, mixed>|null
     */
    public function forAdminPreview(User $admin, int $ssId): ?array
    {
        if (! $admin->hasRole('admin')) {
            return null;
        }

        $year = session('indicator_year');
        if (! $year instanceof Year) {
            return null;
        }

        if ($ssId <= 0 || ! CenHieDependency::query()->whereKey($ssId)->exists()) {
            return null;
        }

        $ttl = (int) config('centinela.dashboard_summary.cache_ttl_seconds', 180);
        $cacheKey = sprintf(
            'centinela:non_admin_dashboard:preview:%s:%d:%d:%d',
            self::CACHE_VERSION,
            $admin->id,
            $year->id,
            $ssId
        );

        $this->previewSsId = $ssId;

        try {
            return Cache::remember($cacheKey, max(30, $ttl), function () use ($admin, $year, $ssId): array {
                $payload = $this->buildPayload($admin, $year, $ssId);
                $payload['preview'] = true;
                $payload['module_enabled'] = (bool) config('centinela.dashboard_non_admin.enabled', true);

                return $payload;
            });
        } finally {
            $this->previewSsId = null;
        }
    }

    /**
     * @return list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string}>
     */
    public function detailRows(User $user, string $section, ?string $slug = null, ?int $previewSsId = null): array
    {
        if ($previewSsId !== null) {
            if (! $user->hasRole('admin')) {
                return [];
            }

            if (! session('indicator_year') instanceof Year) {
                return [];
            }

            $this->previewSsId = $previewSsId;
        } else {
            $payload = $this->forUser($user);
            if ($payload === null) {
                return [];
            }
        }

        try {
            $year = session('indicator_year');
            $payload = ($previewSsId !== null && $year instanceof Year)
                ? $this->buildPayload($user, $year, $previewSsId)
                : ($this->forUser($user) ?? []);

            return match ($section) {
                'ambitos' => $this->detailAmbitoRows($user, $slug, $payload),
                'instrumentos' => $this->detailInstrumentRows($user, $slug),
                'establecimientos' => $this->detailEstablishmentIndicatorRows(
                    $slug !== null && ctype_digit((string) $slug) ? (int) $slug : null,
                    $year instanceof Year ? $year : null,
                    $previewSsId !== null ? $previewSsId : (int) $user->cen_hie_dependency_id,
                ),
                'alertas' => array_map(
                    fn (array $alert): array => [
                        'label' => (string) ($alert['title'] ?? ''),
                        'percent' => null,
                        'establishment' => (string) ($alert['body'] ?? ''),
                        'url' => $alert['url'] ?? null,
                        'source' => 'alerta',
                    ],
                    $payload['alertas'] ?? []
                ),
                default => [],
            };
        } finally {
            if ($previewSsId !== null) {
                $this->previewSsId = null;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(User $user, Year $year, ?int $hieIdOverride = null): array
    {
        $hieId = $hieIdOverride ?? $this->resolveHieId($user);
        $ss = CenHieDependency::query()->find($hieId);

        $this->resetRequestCaches();

        $metIndicators = $this->resolveEnabledMetIndicators($year, $hieId);
        $metCompliance = $this->resolveMetComplianceBatch($metIndicators, $year, $hieId);

        $commitments = $this->loadDashboardCommitments($year, $hieId);
        $comgesEnabled = $this->comgesEnabledForDashboard($year, $hieId);
        $comgesPercentMap = $comgesEnabled
            ? app(ComgesResultsService::class)->latestIndicatorCompliancePercentMap($commitments, $year, $hieId)
            : [];

        $ambitos = $this->buildAmbitoWidgets(
            $commitments,
            $metIndicators,
            $metCompliance,
            $comgesPercentMap,
            $comgesEnabled,
        );
        $instrumentos = $this->buildInstrumentWidgets($user, $commitments, $year, $hieId);
        $topEstablecimientos = $this->buildTopEstablishments($year, $hieId, $metCompliance);
        $alertas = $this->buildAlerts($user, $commitments, $hieId, $year, $metIndicators);
        $destacados = $this->buildPinnedHighlights($user, $year);

        return [
            'context' => [
                'year_ano' => $year->ano,
                'ss_nombre' => $ss?->nombre ?? 'Servicio #'.$hieId,
            ],
            'ambitos' => $ambitos,
            'instrumentos' => $instrumentos,
            'top_establecimientos' => $topEstablecimientos,
            'alertas' => $alertas,
            'destacados' => $destacados,
            'detail_ambito_indicators' => $this->buildDetailAmbitoIndicators(
                $commitments,
                $metIndicators,
                $metCompliance,
                $comgesPercentMap,
                $comgesEnabled,
            ),
        ];
    }

    private function resetRequestCaches(): void
    {
        $this->cachedMetIndicators = null;
        $this->cachedMetCompliance = null;
        $this->cachedCommitments = null;
        $this->instrumentEnabledMemo = [];
        $this->enabledIndicatorTypeIdsByContext = [];
    }

    /**
     * @return Collection<int, MetIndicator>
     */
    private function resolveEnabledMetIndicators(Year $year, int $hieId): Collection
    {
        if ($this->cachedMetIndicators === null) {
            $this->cachedMetIndicators = $this->enabledDashboardMetIndicators($year, $hieId);
        }

        return $this->cachedMetIndicators;
    }

    /**
     * @param  Collection<int, MetIndicator>  $metIndicators
     * @return array{
     *     promedio_by_indicator_id: array<int, float|null>,
     *     payload_by_indicator_id: array<int, array<string, mixed>>,
     *     compliance_by_center: array<int, array{percents: list<float>, indicator_count: int}>
     * }
     */
    private function resolveMetComplianceBatch(Collection $metIndicators, Year $year, int $hieId): array
    {
        if ($this->cachedMetCompliance === null) {
            $this->cachedMetCompliance = app(MetDashboardComplianceAggregator::class)
                ->aggregate($metIndicators, $hieId, $year->id);
        }

        return $this->cachedMetCompliance;
    }

    /**
     * @return Collection<int, ComCommitment>
     */
    private function loadDashboardCommitments(Year $year, int $hieId): Collection
    {
        if ($this->cachedCommitments !== null) {
            return $this->cachedCommitments;
        }

        $this->cachedCommitments = ComCommitment::query()
            ->where('year_id', $year->id)
            ->orderBy('orden')
            ->with([
                'comIndicators.indicatorAmbitos',
                'comIndicators.comIndicatorCutoffs.cutoff',
                'comIndicators.comIndicatorCutoffs.comActions.comEvaluations' => function ($query) use ($hieId): void {
                    $query->where('cen_hie_dependency_id', $hieId);
                },
                'comIndicators.comIndicatorCutoffs.comActions.comVerifiableDocuments' => function ($query) use ($hieId): void {
                    $query->where('cen_hie_dependency_id', $hieId);
                },
                'comIndicators.comIndicatorCutoffs.comActions.comSensitivityTables.comSensitivityDependencies',
            ])
            ->get();

        return $this->cachedCommitments;
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     * @param  Collection<int, MetIndicator>  $metIndicators
     * @param  array{
     *     promedio_by_indicator_id: array<int, float|null>,
     *     payload_by_indicator_id: array<int, array<string, mixed>>,
     *     compliance_by_center: array<int, array{percents: list<float>, indicator_count: int}>
     * }  $metCompliance
     * @param  array<int, float|null>  $comgesPercentMap
     * @return list<array{id: int|null, slug: string, label: string, percent: float|null, indicator_count: int, detail_url: string}>
     */
    private function buildAmbitoWidgets(
        Collection $commitments,
        Collection $metIndicators,
        array $metCompliance,
        array $comgesPercentMap,
        bool $comgesEnabled,
    ): array {
        if (! Schema::hasTable('indicator_ambitos')) {
            return [];
        }

        $comIndicators = $commitments
            ->flatMap(fn (ComCommitment $c) => $c->comIndicators)
            ->unique('id')
            ->values();

        $catalog = IndicatorAmbito::query()->activo()->ordered()->get();
        $buckets = [];

        foreach ($catalog as $ambito) {
            $buckets[$ambito->id] = [
                'id' => $ambito->id,
                'slug' => $ambito->slug,
                'label' => $ambito->nombre,
                'percents' => [],
                'indicator_count' => 0,
            ];
        }

        $sinAmbitoKey = 'none';
        $buckets[$sinAmbitoKey] = [
            'id' => null,
            'slug' => 'sin-ambito',
            'label' => 'Sin ámbito',
            'percents' => [],
            'indicator_count' => 0,
        ];

        if ($comgesEnabled) {
            foreach ($comIndicators as $indicator) {
                $percent = $comgesPercentMap[(int) $indicator->id] ?? null;
                $this->assignIndicatorToAmbitoBuckets($buckets, $indicator->indicatorAmbitos, $percent, $sinAmbitoKey);
            }
        }

        $promedioByIndicatorId = $metCompliance['promedio_by_indicator_id'];

        foreach ($metIndicators as $indicator) {
            $percent = $promedioByIndicatorId[(int) $indicator->id] ?? null;
            $this->assignIndicatorToAmbitoBuckets($buckets, $indicator->indicatorAmbitos, $percent, $sinAmbitoKey);
        }

        $widgets = [];
        foreach ($catalog as $ambito) {
            if (! isset($buckets[$ambito->id])) {
                continue;
            }
            $widget = $this->finalizeAmbitoWidget($buckets[$ambito->id]);
            if ($widget !== null) {
                $widgets[] = $widget;
            }
        }

        $sinAmbitoWidget = $this->finalizeAmbitoWidget($buckets[$sinAmbitoKey]);
        if ($sinAmbitoWidget !== null) {
            $widgets[] = $sinAmbitoWidget;
        }

        return $this->sortByCompliancePercentDesc($widgets);
    }

    /**
     * @param  list<array{label: string, percent: float|null, ...}>  $rows
     * @return list<array{label: string, percent: float|null, ...}>
     */
    private function sortByCompliancePercentDesc(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $percentA = $a['percent'];
            $percentB = $b['percent'];

            if ($percentA === null && $percentB === null) {
                return strcmp((string) $a['label'], (string) $b['label']);
            }
            if ($percentA === null) {
                return 1;
            }
            if ($percentB === null) {
                return -1;
            }
            if ($percentA !== $percentB) {
                return $percentB <=> $percentA;
            }

            return strcmp((string) $a['label'], (string) $b['label']);
        });

        return $rows;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $buckets
     * @param  Collection<int, IndicatorAmbito>  $ambitos
     */
    private function assignIndicatorToAmbitoBuckets(
        array &$buckets,
        Collection $ambitos,
        ?float $percent,
        string $sinAmbitoKey,
    ): void {
        $targets = $ambitos->isEmpty() ? collect([null]) : $ambitos;

        foreach ($targets as $ambito) {
            $key = $ambito?->id ?? $sinAmbitoKey;
            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['indicator_count']++;
            if ($percent !== null) {
                $buckets[$key]['percents'][] = $percent;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @return array{id: int|null, slug: string, label: string, percent: float|null, indicator_count: int, detail_url: string}|null
     */
    private function finalizeAmbitoWidget(array $bucket): ?array
    {
        if ((int) $bucket['indicator_count'] === 0) {
            return null;
        }

        $percents = $bucket['percents'];
        $slug = (string) $bucket['slug'];

        return [
            'id' => $bucket['id'],
            'slug' => $slug,
            'label' => (string) $bucket['label'],
            'percent' => $percents === [] ? null : round(array_sum($percents) / count($percents), 1),
            'indicator_count' => (int) $bucket['indicator_count'],
            'detail_url' => $this->dashboardDetailUrl('ambitos', $slug),
        ];
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     * @return list<array{slug: string, label: string, percent: float|null, indicator_count: int, detail_url: string, visible: bool}>
     */
    private function buildInstrumentWidgets(User $user, Collection $commitments, Year $year, int $hieId): array
    {
        $widgets = [];

        $comgesType = IndicatorType::query()->where('route', 'voyager.compromisos.index')->first();
        if ($comgesType !== null && $this->instrumentEnabledForDashboard($comgesType, 'comges', $hieId, $year)) {
            $comgesIndicatorCount = $commitments
                ->flatMap(fn (ComCommitment $commitment) => $commitment->comIndicators)
                ->unique('id')
                ->count();

            $widgets[] = [
                'slug' => 'comges',
                'label' => 'COMGES',
                'percent' => app(ComgesResultsService::class)->latestGlobalCompliancePercent($commitments, $year, $hieId),
                'indicator_count' => $comgesIndicatorCount,
                'detail_url' => $this->voyagerCompromisosIndexUrl(),
                'visible' => true,
            ];
        }

        $metIndicatorCounts = MetIndicator::query()
            ->where('year_id', $year->id)
            ->where('estado', true)
            ->selectRaw('indicator_type_id, COUNT(*) as aggregate')
            ->groupBy('indicator_type_id')
            ->pluck('aggregate', 'indicator_type_id');

        $metasTypes = IndicatorType::query()
            ->where('route', 'like', 'metas-%')
            ->orderBy('orden')
            ->get();

        foreach ($metasTypes as $type) {
            if (! $this->instrumentEnabledForDashboard($type, $type->route, $hieId, $year)) {
                continue;
            }

            $widgets[] = [
                'slug' => $type->route,
                'label' => (string) $type->nombre_indicador,
                'percent' => app(MetasEstablishmentLatestEvaluationService::class)
                    ->averageLatestGlobalPercent($type, $year, $hieId),
                'indicator_count' => (int) ($metIndicatorCounts[$type->id] ?? 0),
                'detail_url' => $this->metasInstrumentIndexUrl($type),
                'visible' => true,
            ];
        }

        $adpType = IndicatorType::query()->where('route', 'adp')->first();
        if ($adpType !== null && $this->instrumentEnabledForDashboard($adpType, 'adp', $hieId, $year)) {
            $adpPercent = AdpAgreementPeriodResult::query()
                ->whereHas('agreementPeriod.agreement.assignment', fn ($q) => $q->where('cen_hie_dependency_id', $hieId))
                ->whereNotNull('weighted_global_percent')
                ->avg('weighted_global_percent');

            $adpIndicatorCount = AdpAgreement::query()
                ->where('cen_hie_dependency_id', $hieId)
                ->count();

            $widgets[] = [
                'slug' => 'adp',
                'label' => 'ADP',
                'percent' => $adpPercent !== null ? round((float) $adpPercent, 1) : null,
                'indicator_count' => $adpIndicatorCount,
                'detail_url' => $this->adpInstrumentIndexUrl(),
                'visible' => true,
            ];
        }

        return $widgets;
    }

    private function instrumentEnabledForDashboard(
        IndicatorType $type,
        string $moduleKey,
        int $hieId,
        Year $year,
    ): bool {
        $memoKey = $hieId.':'.$year->id.':'.$type->id;
        if (array_key_exists($memoKey, $this->instrumentEnabledMemo)) {
            return $this->instrumentEnabledMemo[$memoKey];
        }

        if (! ServiceModuleToggle::isEnabled($hieId, $moduleKey)) {
            return $this->instrumentEnabledMemo[$memoKey] = false;
        }

        $contextKey = $hieId.':'.$year->id;
        if (! array_key_exists($contextKey, $this->enabledIndicatorTypeIdsByContext)) {
            $this->enabledIndicatorTypeIdsByContext[$contextKey] = IndicatorTypeSetting::query()
                ->where('year_id', $year->id)
                ->where('cen_hie_dependency_id', $hieId)
                ->where('habilitado', true)
                ->pluck('indicator_type_id')
                ->mapWithKeys(fn (int $typeId): array => [$typeId => true])
                ->all();
        }

        return $this->instrumentEnabledMemo[$memoKey] = isset(
            $this->enabledIndicatorTypeIdsByContext[$contextKey][(int) $type->id]
        );
    }

    public function instrumentModuleUrl(User $user, ?string $slug): ?string
    {
        $slug = $slug ?? 'comges';

        if ($slug === 'comges') {
            return $this->voyagerCompromisosIndexUrl();
        }

        if ($slug === 'adp') {
            return $this->adpInstrumentIndexUrl();
        }

        $type = IndicatorType::query()->where('route', $slug)->first();
        if ($type === null) {
            return null;
        }

        return $this->metasInstrumentIndexUrl($type);
    }

    public function establishmentDetailTitle(int $centerId): string
    {
        $center = CenCenter::query()->find($centerId);

        return $center !== null ? (string) $center->nombre_corto : ('Centro #'.$centerId);
    }

    /**
     * @return Collection<int, MetIndicator>
     */
    private function enabledDashboardMetIndicators(Year $year, int $hieId): Collection
    {
        return MetIndicator::query()
            ->where('year_id', $year->id)
            ->where('estado', true)
            ->with(['indicatorAmbitos', 'indicatorType', 'dataFields', 'formula'])
            ->orderBy('orden')
            ->get()
            ->filter(function (MetIndicator $indicator) use ($year, $hieId): bool {
                $type = $indicator->indicatorType;
                if ($type === null || ! str_starts_with((string) $type->route, 'metas-')) {
                    return false;
                }

                return $this->instrumentEnabledForDashboard($type, (string) $type->route, $hieId, $year);
            })
            ->values();
    }

    /**
     * @param  array{
     *     promedio_by_indicator_id: array<int, float|null>,
     *     payload_by_indicator_id: array<int, array<string, mixed>>,
     *     compliance_by_center: array<int, array{percents: list<float>, indicator_count: int}>
     * }  $metCompliance
     * @return list<array{rank: int, center_label: string, percent: float|null, indicator_count: int, detail_url: string|null}>
     */
    private function buildTopEstablishments(Year $year, int $hieId, array $metCompliance): array
    {
        $evaluationService = app(MetasEstablishmentLatestEvaluationService::class);

        $centerIds = $evaluationService->allEvaluatedCenterIds($year, $hieId);
        if ($centerIds->isEmpty()) {
            return [];
        }

        $complianceByCenter = $metCompliance['compliance_by_center'];
        $centers = CenCenter::query()
            ->whereIn('id', $centerIds)
            ->get()
            ->keyBy('id');

        $items = $centerIds
            ->map(function (int $centerId) use ($complianceByCenter, $centers): array {
                $stats = $complianceByCenter[$centerId] ?? ['percents' => [], 'indicator_count' => 0];
                $values = $stats['percents'];
                $percent = $values === [] ? null : round(array_sum($values) / count($values), 1);
                $center = $centers->get($centerId);
                $label = $center !== null ? (string) $center->nombre_corto : ('Centro #'.$centerId);

                return [
                    'center_id' => $centerId,
                    'center_label' => $label,
                    'percent' => $percent,
                    'indicator_count' => (int) $stats['indicator_count'],
                    'sort_percent' => $percent ?? -1.0,
                    'sort_label' => $label,
                ];
            })
            ->sortBy([
                ['sort_percent', 'desc'],
                ['sort_label', 'asc'],
            ])
            ->values();

        $result = [];
        $rank = 1;
        foreach ($items as $item) {
            $result[] = [
                'rank' => $rank,
                'center_id' => $item['center_id'],
                'center_label' => $item['center_label'],
                'percent' => $item['percent'],
                'indicator_count' => $item['indicator_count'],
                'detail_url' => $this->dashboardDetailUrl('establecimientos', (string) $item['center_id']),
            ];
            $rank++;
        }

        return $result;
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     * @param  Collection<int, MetIndicator>  $metIndicators
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    private function buildAlerts(
        User $user,
        Collection $commitments,
        int $hieId,
        Year $year,
        Collection $metIndicators,
    ): array {
        $alerts = [];

        if (ComgesYearMode::usesScoreScale($year)) {
            // Evitar Helper::showCurrentYear() (sesión): agentes usan YearContext por request.
            $setting = IndicatorTypeSetting::query()
                ->where('indicator_type_id', 1)
                ->where('year_id', $year->id)
                ->where('cen_hie_dependency_id', $hieId)
                ->first();
            $context = new AlertContext(
                user: $user,
                year: $year,
                surface: AlertSurface::Dashboard,
                usesScoreScale: true,
                cenHieDependencyId: $hieId,
                comgesEtapaVigente: $setting?->etapa_vigente,
            );

            $alertService = app(CentinelaVisualAlertService::class);
            if ($commitments instanceof EloquentCollection) {
                $commitments->loadMissing(
                    $alertService->requiredEagerLoads($context, VisualAlertModule::Comges->value)
                );
            }
            $collected = $alertService->collectForModule(
                $context,
                VisualAlertModule::Comges->value,
                $commitments,
            );

            $alerts = $alertService->toDashboardListPayload($collected);
        }

        return array_merge(
            $alerts,
            app(MetIndicatorFormulaIssueService::class)->dashboardAlertsForIndicators($metIndicators),
            app(MetIndicatorCalculationErrorService::class)->dashboardAlertsForIndicators(
                $metIndicators,
                $hieId,
                $year->id,
            ),
            app(MetIndicatorIncompletePeriodService::class)->dashboardAlertsForIndicators(
                $metIndicators,
                $hieId,
                $year->id,
            ),
            app(SupportTicketDashboardAlertService::class)->dashboardAlertsForUser($user, $hieId),
        );
    }

    /**
     * @return list<array{title: string, body: string, module: string, author: string, registered_at: string, url: string}>
     */
    private function buildPinnedHighlights(User $user, Year $year): array
    {
        if (! Schema::hasTable('indicator_activities')) {
            return [];
        }

        $comIndicatorIds = ComIndicator::query()
            ->whereHas('comCommitment', fn ($query) => $query->where('year_id', $year->id))
            ->pluck('id');

        $metIndicatorIds = MetIndicator::query()
            ->where('year_id', $year->id)
            ->pluck('id');

        if ($comIndicatorIds->isEmpty() && $metIndicatorIds->isEmpty()) {
            return [];
        }

        $activities = IndicatorActivity::query()
            ->where('is_pinned', true)
            ->visibleToUser($user)
            ->where(function ($query) use ($comIndicatorIds, $metIndicatorIds): void {
                if ($comIndicatorIds->isNotEmpty()) {
                    $query->where(function ($sub) use ($comIndicatorIds): void {
                        $sub->where('indicator_type', IndicatorModuleType::Comges)
                            ->whereIn('indicator_id', $comIndicatorIds);
                    });
                }

                if ($metIndicatorIds->isNotEmpty()) {
                    $query->orWhere(function ($sub) use ($metIndicatorIds): void {
                        $sub->where('indicator_type', IndicatorModuleType::Metas)
                            ->whereIn('indicator_id', $metIndicatorIds);
                    });
                }
            })
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $highlights = [];
        foreach ($activities as $activity) {
            $mailData = IndicatorNoteMailDataFactory::fromActivity($activity);
            $highlights[] = [
                'title' => '['.$mailData->indicatorClave.'] '.$mailData->indicatorNombre,
                'body' => $mailData->noteText,
                'module' => $mailData->moduleLabel,
                'author' => $mailData->authorName,
                'registered_at' => $mailData->registeredAt,
                'url' => $mailData->bitacoraUrl,
            ];
        }

        return $highlights;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string}>
     */
    private function detailAmbitoRows(User $user, ?string $slug, array $payload = []): array
    {
        if (isset($payload['detail_ambito_indicators']) && is_array($payload['detail_ambito_indicators'])) {
            $rows = $this->filterDetailAmbitoIndicatorsBySlug($payload['detail_ambito_indicators'], $slug);

            return $this->appendAmbitoDetailSummaryRow(
                $this->sortByCompliancePercentDesc($rows),
            );
        }

        $year = session('indicator_year');
        if (! $year instanceof Year) {
            return [];
        }

        $hieId = $this->resolveHieId($user);

        $commitments = $this->loadDashboardCommitments($year, $hieId);
        $metIndicators = $this->resolveEnabledMetIndicators($year, $hieId);
        $metCompliance = $this->resolveMetComplianceBatch($metIndicators, $year, $hieId);
        $comgesEnabled = $this->comgesEnabledForDashboard($year, $hieId);
        $comgesPercentMap = $comgesEnabled
            ? app(ComgesResultsService::class)->latestIndicatorCompliancePercentMap($commitments, $year, $hieId)
            : [];

        $rows = [];

        if ($comgesEnabled) {
            foreach ($commitments->flatMap->comIndicators as $indicator) {
                if (! $this->indicatorMatchesAmbitoSlug($indicator->indicatorAmbitos, $slug)) {
                    continue;
                }
                $rows[] = [
                    'label' => '['.$indicator->clave.'] '.$indicator->nombre,
                    'percent' => $comgesPercentMap[(int) $indicator->id] ?? null,
                    'establishment' => 'Última evaluación',
                    'url' => $this->comgesIndicatorShowUrl((int) $indicator->id),
                    'source' => 'COMGES',
                ];
            }
        }

        $promedioByIndicatorId = $metCompliance['promedio_by_indicator_id'];

        foreach ($metIndicators as $indicator) {
            if (! $this->indicatorMatchesAmbitoSlug($indicator->indicatorAmbitos, $slug)) {
                continue;
            }

            $rows[] = [
                'label' => '['.$indicator->clave.'] '.$indicator->nombre,
                'percent' => $promedioByIndicatorId[(int) $indicator->id] ?? null,
                'establishment' => 'Promedio de cumplimiento',
                'url' => $this->metasIndicatorShowUrl($indicator),
                'source' => 'Metas',
            ];
        }

        return $this->appendAmbitoDetailSummaryRow(
            $this->sortByCompliancePercentDesc($rows),
        );
    }

    /**
     * @param  list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string, is_summary?: bool}>  $rows
     * @return list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string, is_summary?: bool}>
     */
    private function appendAmbitoDetailSummaryRow(array $rows): array
    {
        $percents = array_values(array_filter(
            array_map(fn (array $row): ?float => $row['percent'] ?? null, $rows),
            fn (?float $percent): bool => $percent !== null,
        ));

        if ($percents === []) {
            return $rows;
        }

        $rows[] = [
            'label' => 'Cumplimiento promedio',
            'percent' => round(array_sum($percents) / count($percents), 1),
            'establishment' => null,
            'url' => null,
            'source' => '',
            'is_summary' => true,
        ];

        return $rows;
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     * @param  Collection<int, MetIndicator>  $metIndicators
     * @param  array{
     *     promedio_by_indicator_id: array<int, float|null>,
     *     payload_by_indicator_id: array<int, array<string, mixed>>,
     *     compliance_by_center: array<int, array{percents: list<float>, indicator_count: int}>
     * }  $metCompliance
     * @param  array<int, float|null>  $comgesPercentMap
     * @return list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string, ambito_slugs: list<string>}>
     */
    private function buildDetailAmbitoIndicators(
        Collection $commitments,
        Collection $metIndicators,
        array $metCompliance,
        array $comgesPercentMap,
        bool $comgesEnabled,
    ): array {
        $rows = [];

        if ($comgesEnabled) {
            foreach ($commitments->flatMap->comIndicators as $indicator) {
                $rows[] = [
                    'label' => '['.$indicator->clave.'] '.$indicator->nombre,
                    'percent' => $comgesPercentMap[(int) $indicator->id] ?? null,
                    'establishment' => 'Última evaluación',
                    'url' => $this->comgesIndicatorShowUrl((int) $indicator->id),
                    'source' => 'COMGES',
                    'ambito_slugs' => $this->ambitoSlugsForIndicator($indicator->indicatorAmbitos),
                ];
            }
        }

        $promedioByIndicatorId = $metCompliance['promedio_by_indicator_id'];

        foreach ($metIndicators as $indicator) {
            $rows[] = [
                'label' => '['.$indicator->clave.'] '.$indicator->nombre,
                'percent' => $promedioByIndicatorId[(int) $indicator->id] ?? null,
                'establishment' => 'Promedio de cumplimiento',
                'url' => $this->metasIndicatorShowUrl($indicator),
                'source' => 'Metas',
                'ambito_slugs' => $this->ambitoSlugsForIndicator($indicator->indicatorAmbitos),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string, ambito_slugs: list<string>}>  $indicators
     * @return list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string}>
     */
    private function filterDetailAmbitoIndicatorsBySlug(array $indicators, ?string $slug): array
    {
        $slug = $slug ?? 'sin-ambito';
        $rows = [];

        foreach ($indicators as $row) {
            if (! $this->rowMatchesAmbitoSlug($row['ambito_slugs'] ?? [], $slug)) {
                continue;
            }

            unset($row['ambito_slugs']);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  Collection<int, IndicatorAmbito>  $ambitos
     * @return list<string>
     */
    private function ambitoSlugsForIndicator(Collection $ambitos): array
    {
        return $ambitos->pluck('slug')->all();
    }

    /**
     * @param  list<string>  $ambitoSlugs
     */
    private function rowMatchesAmbitoSlug(array $ambitoSlugs, string $slug): bool
    {
        if ($slug === 'sin-ambito') {
            return $ambitoSlugs === [];
        }

        return in_array($slug, $ambitoSlugs, true);
    }

    /**
     * @param  Collection<int, IndicatorAmbito>  $ambitos
     */
    private function indicatorMatchesAmbitoSlug(Collection $ambitos, ?string $slug): bool
    {
        return $this->rowMatchesAmbitoSlug($this->ambitoSlugsForIndicator($ambitos), $slug ?? 'sin-ambito');
    }

    /**
     * @return list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string}>
     */
    private function detailInstrumentRows(User $user, ?string $slug): array
    {
        $slug = $slug ?? 'comges';
        $year = session('indicator_year');
        if (! $year instanceof Year) {
            return [];
        }

        $hieId = $this->resolveHieId($user);

        if ($slug === 'comges') {
            $commitments = ComCommitment::query()
                ->where('year_id', $year->id)
                ->orderBy('orden')
                ->get();

            return $commitments->map(fn (ComCommitment $c) => [
                'label' => (string) ($c->nombre ?: $c->clave),
                'percent' => $this->scoreToPercent($c->puntajePonderado('FINAL')),
                'establishment' => null,
                'url' => $this->voyagerCompromisosEditUrl($c->id),
                'source' => 'COMGES',
            ])->values()->all();
        }

        if ($slug === 'adp') {
            return AdpAgreementPeriodResult::query()
                ->with([
                    'agreementPeriod.agreement.assignment.position',
                    'agreementPeriod.agreement.assignment.center',
                ])
                ->whereHas('agreementPeriod.agreement.assignment', fn ($q) => $q->where('cen_hie_dependency_id', $hieId))
                ->orderByDesc('weighted_global_percent')
                ->limit(50)
                ->get()
                ->map(function (AdpAgreementPeriodResult $row): array {
                    $assignment = $row->agreementPeriod?->agreement?->assignment;
                    $center = $assignment?->center;

                    return [
                        'label' => (string) ($assignment?->position?->nombre ?? 'Convenio'),
                        'percent' => $row->weighted_global_percent !== null
                            ? round((float) $row->weighted_global_percent, 1)
                            : null,
                        'establishment' => $center !== null ? (string) $center->nombre_corto : null,
                        'url' => $this->adpInstrumentIndexUrl(),
                        'source' => 'ADP',
                    ];
                })
                ->all();
        }

        $type = IndicatorType::query()->where('route', $slug)->first();
        if ($type === null) {
            return [];
        }

        $periodoRank = $this->metEstablishmentPeriodoRank();
        $hubUrl = $this->metasInstrumentIndexUrl($type);

        return MetEstablishmentResult::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $hieId)
            ->where('indicator_type_id', $type->id)
            ->with('center')
            ->get()
            ->groupBy(fn (MetEstablishmentResult $r) => $r->cen_center_id.'-'.$r->indicator_type_id)
            ->map(function ($group) use ($periodoRank): ?MetEstablishmentResult {
                if ($group->isEmpty()) {
                    return null;
                }

                return $group->sort(function (MetEstablishmentResult $a, MetEstablishmentResult $b) use ($periodoRank): int {
                    $oa = $periodoRank[(string) $a->periodo] ?? 0;
                    $ob = $periodoRank[(string) $b->periodo] ?? 0;
                    if ($oa !== $ob) {
                        return $ob <=> $oa;
                    }

                    return $b->id <=> $a->id;
                })->first();
            })
            ->filter()
            ->map(function (MetEstablishmentResult $row) use ($hubUrl): array {
                $center = $row->center;

                return [
                    'label' => $center !== null ? (string) $center->nombre_corto : ('Centro #'.$row->cen_center_id),
                    'percent' => $row->resultado_global !== null
                        ? round((float) $row->resultado_global, 1)
                        : null,
                    'establishment' => (string) $row->periodo,
                    'url' => $hubUrl,
                    'source' => 'Metas',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string, is_summary?: bool}>
     */
    private function detailEstablishmentIndicatorRows(?int $centerId, ?Year $year, int $hieId): array
    {
        if ($centerId === null || ! $year instanceof Year) {
            return [];
        }

        $metIndicators = $this->resolveEnabledMetIndicators($year, $hieId);
        $metCompliance = $this->resolveMetComplianceBatch($metIndicators, $year, $hieId);
        $payloadByIndicatorId = $metCompliance['payload_by_indicator_id'];
        $rows = [];

        foreach ($metIndicators as $indicator) {
            $payload = $payloadByIndicatorId[(int) $indicator->id] ?? null;
            if ($payload === null) {
                continue;
            }

            $establishmentRow = collect($payload['rows'])->firstWhere('cen_center_id', $centerId);
            if ($establishmentRow === null) {
                continue;
            }

            $type = $indicator->indicatorType;
            $rows[] = [
                'label' => '['.$indicator->clave.'] '.$indicator->nombre,
                'percent' => $establishmentRow['cumplimiento'] !== null
                    ? round((float) $establishmentRow['cumplimiento'], 1)
                    : null,
                'establishment' => $type?->nombre_indicador ?? 'Metas',
                'url' => $this->metasIndicatorShowUrl($indicator),
                'source' => 'Metas',
            ];
        }

        return $this->appendAmbitoDetailSummaryRow($rows);
    }

    private function comgesEnabledForDashboard(Year $year, int $hieId): bool
    {
        $comgesType = IndicatorType::query()->where('route', 'voyager.compromisos.index')->first();
        if ($comgesType === null) {
            return false;
        }

        return $this->instrumentEnabledForDashboard($comgesType, 'comges', $hieId, $year);
    }

    private function scoreToPercent(?float $puntajeOnFourScale): ?float
    {
        if ($puntajeOnFourScale === null) {
            return null;
        }

        return round(($puntajeOnFourScale / 4) * 100, 1);
    }

    private function voyagerCompromisosEditUrl(int $commitmentId): string
    {
        if (Route::has('voyager.compromisos.edit')) {
            return route('voyager.compromisos.edit', $commitmentId);
        }

        return url('/admin/compromisos/'.$commitmentId.'/edit');
    }

    private function comgesIndicatorShowUrl(int $indicatorId): string
    {
        if (Route::has('voyager.indicadores-comges.show')) {
            return route('voyager.indicadores-comges.show', $indicatorId);
        }

        return url('/admin/indicadores-comges/'.$indicatorId);
    }

    private function metasIndicatorShowUrl(MetIndicator $indicator): ?string
    {
        $type = $indicator->indicatorType;
        if ($type === null) {
            return null;
        }

        if (Route::has('metas.instrument.indicators.show')) {
            return route('metas.instrument.indicators.show', [
                'indicatorType' => $type->route,
                'indicator' => $indicator,
            ]);
        }

        return url('/admin/'.$type->route.'/indicadores/'.$indicator->getKey());
    }

    private function voyagerCompromisosIndexUrl(): string
    {
        if (Route::has('voyager.compromisos.index')) {
            return route('voyager.compromisos.index');
        }

        return url('/admin/compromisos');
    }

    private function metasInstrumentIndexUrl(IndicatorType $type): string
    {
        if (Route::has('metas.instrument.index')) {
            return route('metas.instrument.index', $type);
        }

        return url('/admin/'.$type->route);
    }

    private function adpInstrumentIndexUrl(): string
    {
        if (Route::has('adp.instrument.index')) {
            return route('adp.instrument.index');
        }

        return url('/admin/adp');
    }

    /**
     * @return array<string, int>
     */
    private function metEstablishmentPeriodoRank(): array
    {
        return [
            'MES-01' => 1,
            'MES-02' => 2,
            'MES-03' => 3,
            'MES-04' => 4,
            'MES-05' => 5,
            'MES-06' => 6,
            'MES-07' => 7,
            'MES-08' => 8,
            'MES-09' => 9,
            'MES-10' => 10,
            'MES-11' => 11,
            'MES-12' => 12,
            'TRIM-1' => 13,
            'TRIM-2' => 14,
            'TRIM-3' => 15,
            'TRIM-4' => 16,
            'SEM-1' => 17,
            'SEM-2' => 18,
            'ANUAL' => 19,
        ];
    }

    private function resolveMetasHubIndicatorType(): ?IndicatorType
    {
        $preferred = IndicatorType::query()->where('route', 'metas-18834')->where('estado', true)->first();
        if ($preferred !== null) {
            return $preferred;
        }

        return IndicatorType::query()
            ->where('estado', true)
            ->where('route', 'like', 'metas-%')
            ->orderBy('orden')
            ->first();
    }

    private function resolveHieId(User $user): int
    {
        return $this->previewSsId ?? (int) $user->cen_hie_dependency_id;
    }

    private function dashboardDetailUrl(string $section, ?string $slug = null): string
    {
        $params = array_filter([
            'section' => $section,
            'slug' => $slug,
        ], fn ($value) => $value !== null && $value !== '');

        $url = route('centinela.dashboard.detail', $params);

        if ($this->previewSsId !== null) {
            return $url.'?preview=1&ss='.$this->previewSsId;
        }

        return $url;
    }
}
