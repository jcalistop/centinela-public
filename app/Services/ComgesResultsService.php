<?php

namespace App\Services;

use App\Enums\Comges\EvaluacionTipo;
use App\Enums\Corte\CutoffClave;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\ComIndicatorCutoff;
use App\Models\Cutoff;
use App\Models\Year;
use App\Support\Comges\ComgesYearMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ComgesResultsService
{
    /**
     * @var array<string, EvaluacionTipo>
     */
    private const EVALUATION_TYPES = [
        'AUTOEVALUACION' => EvaluacionTipo::Autoevaluacion,
        'PRELIMINAR' => EvaluacionTipo::Preliminar,
        'FINAL' => EvaluacionTipo::Final,
    ];

    /**
     * @var array<string, string>
     */
    private const EVALUATION_RESULT_KEYS = [
        'AUTOEVALUACION' => 'autoevaluacion',
        'PRELIMINAR' => 'evaluacion_preliminar',
        'FINAL' => 'evaluacion_final',
    ];

    public static function usesScoreScale(?Year $year = null): bool
    {
        return ComgesYearMode::usesScoreScale($year);
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     * @return array{
     *     mode: 'percentage'|'score',
     *     cutoffs: array<int, Cutoff>,
     *     puntaje_maximo: array<int, float>,
     *     autoevaluacion: array<int, float|null>,
     *     evaluacion_preliminar: array<int, float|null>,
     *     evaluacion_final: array<int, float|null>
     * }
     */
    public function getGlobalResults(
        Collection $commitments,
        ?Year $year = null,
        ?int $cenHieDependencyId = null,
    ): array {
        $year ??= $this->currentIndicatorYearModel();
        $cenHieDependencyId ??= (int) Auth::user()->cen_hie_dependency_id;

        if (self::usesScoreScale($year)) {
            return $this->aggregateScore($commitments, $cenHieDependencyId);
        }

        return $this->aggregateLegacyPercentage($commitments, $cenHieDependencyId);
    }

    /**
     * Porcentaje de cumplimiento global de la última evaluación disponible
     * (final → preliminar → autoevaluación), alineado al pie de resultados globales COMGES.
     *
     * @param  Collection<int, ComCommitment>  $commitments
     */
    public function latestGlobalCompliancePercent(
        Collection $commitments,
        ?Year $year = null,
        ?int $cenHieDependencyId = null,
    ): ?float {
        $results = $this->getGlobalResults($commitments, $year, $cenHieDependencyId);
        $cutoffs = $results['cutoffs'] ?? [];
        if ($cutoffs === []) {
            return null;
        }

        $totalMaximum = 0.0;
        $stageTotals = [
            'evaluacion_final' => null,
            'evaluacion_preliminar' => null,
            'autoevaluacion' => null,
        ];

        foreach ($cutoffs as $cutoffId => $cutoff) {
            $maximum = (float) ($results['puntaje_maximo'][$cutoffId] ?? 0);
            $totalMaximum += $maximum;

            foreach (array_keys($stageTotals) as $stageKey) {
                $value = $results[$stageKey][$cutoffId] ?? null;
                if ($value !== null) {
                    $stageTotals[$stageKey] = ($stageTotals[$stageKey] ?? 0.0) + (float) $value;
                }
            }
        }

        if ($totalMaximum <= 0) {
            return null;
        }

        foreach (array_keys($stageTotals) as $stageKey) {
            if ($stageTotals[$stageKey] !== null) {
                return round(($stageTotals[$stageKey] / $totalMaximum) * 100, 1);
            }
        }

        return null;
    }

    /**
     * Porcentaje de cumplimiento del indicador según la última evaluación disponible
     * (final → preliminar → autoevaluación), alineado al panel COMGES del dashboard.
     */
    public function latestIndicatorCompliancePercent(
        ComIndicator $indicator,
        ?Year $year = null,
        ?int $cenHieDependencyId = null,
    ): ?float {
        $year ??= $this->currentIndicatorYearModel();
        $cenHieDependencyId ??= (int) Auth::user()->cen_hie_dependency_id;

        if (self::usesScoreScale($year)) {
            foreach (['FINAL', 'PRELIMINAR', 'AUTOEVALUACION'] as $evaluationType) {
                $score = $indicator->puntajePonderado($evaluationType);
                if ($score !== null) {
                    return round(($score / 4) * 100, 1);
                }
            }

            return null;
        }

        $commitment = $indicator->comCommitment;
        if ($commitment === null) {
            return null;
        }

        $isolatedCommitment = $commitment->withoutRelations();
        $isolatedCommitment->setRelation('comIndicators', collect([$indicator]));

        return $this->latestGlobalCompliancePercent(
            collect([$isolatedCommitment]),
            $year,
            $cenHieDependencyId,
        );
    }

    /**
     * Mapa indicador → % de cumplimiento (última evaluación) en un solo pase in-memory.
     *
     * @param  Collection<int, ComCommitment>  $commitments
     * @return array<int, float|null>
     */
    public function latestIndicatorCompliancePercentMap(
        Collection $commitments,
        ?Year $year = null,
        ?int $cenHieDependencyId = null,
    ): array {
        $year ??= $this->currentIndicatorYearModel();
        $cenHieDependencyId ??= (int) Auth::user()->cen_hie_dependency_id;

        $map = [];

        foreach ($commitments->flatMap->comIndicators->unique('id') as $indicator) {
            $map[(int) $indicator->id] = $this->latestIndicatorCompliancePercent(
                $indicator,
                $year,
                $cenHieDependencyId,
            );
        }

        return $map;
    }

    public static function globalResultsCacheKey(int $cenHieDependencyId, int $yearId): string
    {
        return 'comges_global_results_v4_'.$cenHieDependencyId.'_'.$yearId;
    }

    public static function forgetCachedGlobalResults(int $cenHieDependencyId, int $yearId): void
    {
        cache()->forget(self::globalResultsCacheKey($cenHieDependencyId, $yearId));
    }

    public function getCachedGlobalResults(): array
    {
        $cacheKey = self::globalResultsCacheKey(
            (int) Auth::user()->cen_hie_dependency_id,
            $this->currentIndicatorYearModel()->id,
        );

        return cache()->remember($cacheKey, 3600, function () {
            return $this->getGlobalResults($this->getCommitmentsWithRelations());
        });
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     */
    public function getCachedGlobalResultsWithData(Collection $commitments): array
    {
        $cacheKey = self::globalResultsCacheKey(
            (int) Auth::user()->cen_hie_dependency_id,
            $this->currentIndicatorYearModel()->id,
        );

        return cache()->remember($cacheKey, 3600, function () use ($commitments) {
            return $this->getGlobalResults($commitments);
        });
    }

    /**
     * @return Collection<int, ComCommitment>
     */
    public function getCommitmentsWithRelations(): Collection
    {
        return ComCommitment::with([
            'comIndicators' => fn ($query) => $query->orderBy('orden')->orderBy('clave'),
            'comIndicators.comIndicatorCutoffs.cutoff',
            'comIndicators.comIndicatorCutoffs.comActions.comEvaluations' => function ($query) {
                $query->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id);
            },
        ])->currentYear()->orderBy('orden')->get();
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     * @return array{
     *     mode: 'percentage',
     *     cutoffs: array<int, Cutoff>,
     *     puntaje_maximo: array<int, float>,
     *     autoevaluacion: array<int, float>,
     *     evaluacion_preliminar: array<int, float>,
     *     evaluacion_final: array<int, float>
     * }
     */
    private function aggregateLegacyPercentage(Collection $commitments, int $userDependencyId): array
    {
        $results = [
            'mode' => 'percentage',
            'puntaje_maximo' => [],
            'autoevaluacion' => [],
            'evaluacion_preliminar' => [],
            'evaluacion_final' => [],
            'cutoffs' => [],
        ];

        foreach ($commitments as $comCommitment) {
            foreach ($comCommitment->comIndicators as $comIndicator) {
                foreach ($comIndicator->comIndicatorCutoffs as $comIndicatorCutoff) {
                    $cutoffId = $comIndicatorCutoff->cutoff_id;

                    if (! isset($results['cutoffs'][$cutoffId])) {
                        $results['cutoffs'][$cutoffId] = $comIndicatorCutoff->cutoff;
                    }

                    $indicatorWeight = (float) $comIndicator->ponderacion_indicador;

                    foreach ($comIndicatorCutoff->comActions as $comAction) {
                        $evaluations = $comAction->comEvaluations
                            ->where('cen_hie_dependency_id', $userDependencyId);

                        $actionWeight = (float) ($comAction->ponderacion_accion ?? 0) / 100;

                        $results['puntaje_maximo'][$cutoffId][] = $actionWeight * $indicatorWeight;

                        foreach (self::EVALUATION_TYPES as $evaluationType => $enum) {
                            $resultKey = self::EVALUATION_RESULT_KEYS[$evaluationType];
                            $evaluation = $evaluations
                                ->where('tipo_evaluacion', $enum)
                                ->first();

                            $cumplimiento = $evaluation?->cumplimiento;
                            $contribution = $cumplimiento === null
                                ? 0.0
                                : ((float) $cumplimiento / 100) * $actionWeight * $indicatorWeight;

                            $results[$resultKey][$cutoffId][] = $contribution;
                        }
                    }
                }
            }
        }

        $this->sumAggregatedValues($results);

        return $results;
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     * @return array{
     *     mode: 'score',
     *     cutoffs: array<int, Cutoff>,
     *     puntaje_maximo: array<int, float|null>,
     *     autoevaluacion: array<int, float|null>,
     *     evaluacion_preliminar: array<int, float|null>,
     *     evaluacion_final: array<int, float|null>
     * }
     */
    private function aggregateScore(Collection $commitments, int $userDependencyId): array
    {
        $results = [
            'mode' => 'score',
            'puntaje_maximo' => [],
            'autoevaluacion' => [],
            'evaluacion_preliminar' => [],
            'evaluacion_final' => [],
            'cutoffs' => [],
        ];

        foreach ($commitments as $comCommitment) {
            $this->processCommitmentScore($comCommitment, $userDependencyId, $results);
        }

        $this->sumAggregatedValues($results, excludeNullContributions: true);

        return $results;
    }

    /**
     * @param  array<string, mixed>  $results
     */
    private function processCommitmentScore(ComCommitment $comCommitment, int $userDependencyId, array &$results): void
    {
        foreach ($comCommitment->comIndicators as $comIndicator) {
            foreach ($comIndicator->comIndicatorCutoffs as $comIndicatorCutoff) {
                if ($comIndicatorCutoff->cutoff->clave === CutoffClave::Apelacion) {
                    continue;
                }

                if ((bool) $comIndicatorCutoff->evaluacion_local) {
                    continue;
                }

                if (! isset($results['cutoffs'][$comIndicatorCutoff->cutoff_id])) {
                    $results['cutoffs'][$comIndicatorCutoff->cutoff_id] = $comIndicatorCutoff->cutoff;
                }

                $this->processIndicatorCutoffScore($comIndicator, $comIndicatorCutoff, $userDependencyId, $results);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $results
     */
    private function processIndicatorCutoffScore(
        ComIndicator $comIndicator,
        ComIndicatorCutoff $comIndicatorCutoff,
        int $userDependencyId,
        array &$results,
    ): void {
        $puntajeMaximoPorCorte = 4 * ($comIndicatorCutoff->ponderacion_corte / 100) * ($comIndicator->ponderacion_indicador / 100);

        $results['puntaje_maximo'][$comIndicatorCutoff->cutoff_id][] = $puntajeMaximoPorCorte;

        foreach ($comIndicatorCutoff->comActions as $comAction) {
            $evaluations = $comAction->comEvaluations
                ->where('cen_hie_dependency_id', $userDependencyId);

            $actionWeight = (float) ($comAction->ponderacion_accion ?? 0) / 100;

            foreach (self::EVALUATION_TYPES as $evaluationType => $enum) {
                $resultKey = self::EVALUATION_RESULT_KEYS[$evaluationType];
                $evaluation = $evaluations
                    ->where('tipo_evaluacion', $enum)
                    ->first();

                $results[$resultKey][$comIndicatorCutoff->cutoff_id][] = is_null($evaluation)
                    ? null
                    : (is_null($evaluation->cumplimiento)
                        ? null
                        : (float) $evaluation->cumplimiento
                        * ($comIndicatorCutoff->ponderacion_corte / 100)
                        * ($comIndicator->ponderacion_indicador / 100)
                        * $actionWeight);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $results
     */
    private function sumAggregatedValues(array &$results, bool $excludeNullContributions = false): void
    {
        foreach ($results as $key => $data) {
            if (in_array($key, ['cutoffs', 'mode'], true)) {
                continue;
            }

            foreach ($data as $cutoffId => $values) {
                if ($excludeNullContributions) {
                    $filteredValues = array_filter($values, static fn ($value) => $value !== null);

                    $results[$key][$cutoffId] = empty($filteredValues)
                        ? null
                        : round(array_sum($filteredValues), 2);
                } else {
                    $results[$key][$cutoffId] = round(array_sum($values), 2);
                }
            }
        }
    }

    private function currentIndicatorYearModel(): Year
    {
        $year = Session::get('indicator_year');
        abort_unless($year instanceof Year, 500);

        return $year;
    }

    /**
     * @return array<string, mixed>
     */
    public function debugResults(): array
    {
        $commitments = $this->getCommitmentsWithRelations();
        $userDependencyId = Auth::user()->cen_hie_dependency_id;

        $debug = [
            'commitments_count' => $commitments->count(),
            'user_dependency_id' => $userDependencyId,
            'sample_data' => [],
        ];

        if ($commitments->isNotEmpty()) {
            $firstCommitment = $commitments->first();
            $debug['sample_data'] = [
                'commitment_id' => $firstCommitment->id,
                'commitment_clave' => $firstCommitment->clave ?? 'N/A',
                'indicators_count' => $firstCommitment->comIndicators->count(),
            ];

            if ($firstCommitment->comIndicators->isNotEmpty()) {
                $firstIndicator = $firstCommitment->comIndicators->first();
                $debug['sample_data']['first_indicator'] = [
                    'id' => $firstIndicator->id,
                    'clave' => $firstIndicator->clave,
                    'ponderacion' => $firstIndicator->ponderacion_indicador,
                    'cutoffs_count' => $firstIndicator->comIndicatorCutoffs->count(),
                ];

                if ($firstIndicator->comIndicatorCutoffs->isNotEmpty()) {
                    $firstCutoff = $firstIndicator->comIndicatorCutoffs->first();
                    $debug['sample_data']['first_cutoff'] = [
                        'id' => $firstCutoff->id,
                        'cutoff_id' => $firstCutoff->cutoff_id,
                        'cutoff_clave' => $firstCutoff->cutoff->clave ?? 'N/A',
                        'actions_count' => $firstCutoff->comActions->count(),
                    ];

                    if ($firstCutoff->comActions->isNotEmpty()) {
                        $firstAction = $firstCutoff->comActions->first();
                        $evaluations = $firstAction->comEvaluations
                            ->where('cen_hie_dependency_id', $userDependencyId);

                        $debug['sample_data']['first_action'] = [
                            'id' => $firstAction->id,
                            'ponderacion_accion' => $firstAction->ponderacion_accion,
                            'evaluations_count' => $evaluations->count(),
                            'evaluations_details' => $evaluations->map(function ($eval) {
                                return [
                                    'tipo' => $eval->tipo_evaluacion,
                                    'cumplimiento' => $eval->cumplimiento,
                                ];
                            })->toArray(),
                        ];
                    }
                }
            }
        }

        return $debug;
    }
}
