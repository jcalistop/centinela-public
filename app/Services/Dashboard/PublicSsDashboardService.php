<?php

namespace App\Services\Dashboard;

use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetIndicator;
use App\Models\ServiceModuleToggle;
use App\Models\Year;
use App\Services\ComgesResultsService;
use App\Services\Metas\MetasEstablishmentLatestEvaluationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard público (guest): landing de SS y resumen COMGES/Metas por SS+año.
 */
class PublicSsDashboardService
{
    private const CACHE_VERSION = 'v5';

    /**
     * @return list<array{id: int, nombre: string, codigo: string|null, logo_url: string}>
     */
    public function landingServices(): array
    {
        return CenHieDependency::query()
            ->enabled()
            ->get()
            ->map(fn (CenHieDependency $ss): array => [
                'id' => (int) $ss->id,
                'nombre' => (string) $ss->nombre,
                'codigo' => $ss->codigo !== null ? (string) $ss->codigo : null,
                'logo_url' => $ss->logoPublicUrl(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     ss: array{id: int, nombre: string, codigo: string|null, logo_url: string},
     *     year: array{id: int, ano: int},
     *     years: list<array{id: int, ano: int}>,
     *     comges: list<array{id: int, label: string, percent: float|null}>,
     *     comges_percent: float|null,
     *     metas: list<array{
     *         slug: string,
     *         label: string,
     *         indicator_type_id: int,
     *         percent: float|null,
     *         indicator_count: int,
     *         establishments: list<array{id: int, label: string, percent: float|null}>
     *     }>,
     *     comges_visible: bool
     * }
     */
    public function summaryFor(CenHieDependency $ss, Year $year): array
    {
        $ttl = (int) config('centinela.dashboard_summary.cache_ttl_seconds', 180);
        $cacheKey = self::cacheKeyFor((int) $ss->id, (int) $year->id);

        return Cache::remember($cacheKey, max(30, $ttl), function () use ($ss, $year): array {
            return $this->buildSummary($ss, $year);
        });
    }

    public static function cacheKeyFor(int $ssId, int $yearId): string
    {
        return sprintf(
            'centinela:public-ss-dashboard:%s:%d:%d',
            self::CACHE_VERSION,
            $ssId,
            $yearId
        );
    }

    public static function forgetFor(int $ssId, int $yearId): void
    {
        Cache::forget(self::cacheKeyFor($ssId, $yearId));
    }

    public static function forgetForSs(int $ssId): void
    {
        Year::query()
            ->pluck('id')
            ->each(static function ($yearId) use ($ssId): void {
                self::forgetFor($ssId, (int) $yearId);
            });
    }

    /**
     * @return list<array{id: int, ano: int}>
     */
    public function availableYears(): array
    {
        return Year::query()
            ->orderByDesc('ano')
            ->get(['id', 'ano'])
            ->map(fn (Year $y): array => [
                'id' => (int) $y->id,
                'ano' => (int) $y->ano,
            ])
            ->all();
    }

    public function defaultYear(): ?Year
    {
        $default = Year::query()->where('default', true)->first();
        if ($default instanceof Year) {
            return $default;
        }

        return Year::query()->orderByDesc('ano')->first();
    }

    /**
     * @return array{
     *     ss: array{id: int, nombre: string, codigo: string|null, logo_url: string},
     *     year: array{id: int, ano: int},
     *     years: list<array{id: int, ano: int}>,
     *     comges: list<array{id: int, label: string, percent: float|null}>,
     *     comges_percent: float|null,
     *     metas: list<array{
     *         slug: string,
     *         label: string,
     *         indicator_type_id: int,
     *         percent: float|null,
     *         indicator_count: int,
     *         establishments: list<array{id: int, label: string, percent: float|null}>
     *     }>,
     *     comges_visible: bool
     * }
     */
    private function buildSummary(CenHieDependency $ss, Year $year): array
    {
        $hieId = (int) $ss->id;
        $comgesService = app(ComgesResultsService::class);
        $metasService = app(MetasEstablishmentLatestEvaluationService::class);

        $comgesType = IndicatorType::query()->where('route', 'voyager.compromisos.index')->first();
        $comgesVisible = $comgesType !== null
            && $this->instrumentEnabled($comgesType, 'comges', $hieId, $year);

        $comgesRows = [];
        $comgesPercent = null;
        if ($comgesVisible) {
            $commitments = $this->loadCommitments($year, $hieId);
            $comgesPercent = $comgesService->latestGlobalCompliancePercent($commitments, $year, $hieId);
            foreach ($commitments as $commitment) {
                $single = collect([$commitment]);
                $comgesRows[] = [
                    'id' => (int) $commitment->id,
                    'label' => (string) ($commitment->nombre ?: $commitment->clave ?: ('Compromiso #'.$commitment->id)),
                    'percent' => $comgesService->latestGlobalCompliancePercent($single, $year, $hieId),
                ];
            }
        }

        $metIndicatorCounts = MetIndicator::query()
            ->where('year_id', $year->id)
            ->where('estado', true)
            ->selectRaw('indicator_type_id, COUNT(*) as aggregate')
            ->groupBy('indicator_type_id')
            ->pluck('aggregate', 'indicator_type_id');

        $metasRows = [];
        $metasTypes = IndicatorType::query()
            ->where('route', 'like', 'metas-%')
            ->orderBy('orden')
            ->get();

        foreach ($metasTypes as $type) {
            $moduleKey = (string) $type->route;
            if (! $this->instrumentEnabled($type, $moduleKey, $hieId, $year)) {
                continue;
            }

            $byCenter = $metasService->latestGlobalByCenter($type, $year, $hieId);
            $centerIds = array_keys($byCenter);
            $centers = $centerIds === []
                ? collect()
                : CenCenter::query()->whereIn('id', $centerIds)->get()->keyBy('id');

            $establishments = [];
            foreach ($byCenter as $centerId => $percent) {
                $center = $centers->get((int) $centerId);
                $label = $center !== null
                    ? (string) ($center->nombre_corto ?: $center->nombre ?: ('Centro #'.$centerId))
                    : ('Centro #'.$centerId);

                $establishments[] = [
                    'id' => (int) $centerId,
                    'label' => $label,
                    'percent' => $percent,
                    'sort_percent' => $percent ?? -1.0,
                    'sort_label' => $label,
                ];
            }

            usort($establishments, static function (array $a, array $b): int {
                return [$b['sort_percent'], $a['sort_label']] <=> [$a['sort_percent'], $b['sort_label']];
            });

            $establishments = array_map(static function (array $row): array {
                return [
                    'id' => $row['id'],
                    'label' => $row['label'],
                    'percent' => $row['percent'],
                ];
            }, $establishments);

            $metasRows[] = [
                'slug' => $moduleKey,
                'label' => $this->publicMetasLabel((string) $type->nombre_indicador),
                'indicator_type_id' => (int) $type->id,
                'percent' => $metasService->averageLatestGlobalPercent($type, $year, $hieId),
                'indicator_count' => (int) ($metIndicatorCounts[$type->id] ?? 0),
                'establishments' => $establishments,
            ];
        }

        return [
            'ss' => [
                'id' => $hieId,
                'nombre' => (string) $ss->nombre,
                'codigo' => $ss->codigo !== null ? (string) $ss->codigo : null,
                'logo_url' => $ss->logoPublicUrl(),
            ],
            'year' => [
                'id' => (int) $year->id,
                'ano' => (int) $year->ano,
            ],
            'years' => $this->availableYears(),
            'comges' => $comgesRows,
            'comges_percent' => $comgesPercent,
            'metas' => $metasRows,
            'comges_visible' => $comgesVisible,
        ];
    }

    /**
     * @return Collection<int, ComCommitment>
     */
    private function loadCommitments(Year $year, int $hieId): Collection
    {
        return ComCommitment::query()
            ->where('year_id', $year->id)
            ->orderBy('orden')
            ->with([
                'comIndicators.comIndicatorCutoffs.cutoff',
                'comIndicators.comIndicatorCutoffs.comActions.comEvaluations' => function ($query) use ($hieId): void {
                    $query->where('cen_hie_dependency_id', $hieId);
                },
            ])
            ->get();
    }

    private function instrumentEnabled(
        IndicatorType $type,
        string $moduleKey,
        int $hieId,
        Year $year,
    ): bool {
        if (! ServiceModuleToggle::isEnabled($hieId, $moduleKey)) {
            return false;
        }

        return IndicatorTypeSetting::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $hieId)
            ->where('indicator_type_id', $type->id)
            ->where('habilitado', true)
            ->exists();
    }

    private function publicMetasLabel(string $nombreIndicador): string
    {
        $normalized = preg_replace('/\bMetas\s+Sanitarias\b/iu', 'Metas', $nombreIndicador);

        return is_string($normalized) ? trim($normalized) : $nombreIndicador;
    }
}
