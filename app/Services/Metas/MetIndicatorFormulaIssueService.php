<?php

namespace App\Services\Metas;

use App\Models\MetIndicator;
use Illuminate\Support\Collection;

class MetIndicatorFormulaIssueService
{
    public const KIND_MISSING = 'missing';

    public const KIND_INVALID = 'invalid';

    public const KIND_UNKNOWN_VARIABLES = 'unknown_variables';

    /**
     * @return array{kind: string, message: string}|null
     */
    public function issueForIndicator(MetIndicator $indicator): ?array
    {
        if (! $indicator->relationLoaded('dataFields')) {
            $indicator->load('dataFields');
        }

        if ($indicator->dataFields->isEmpty()) {
            return null;
        }

        if (! $indicator->relationLoaded('formula')) {
            $indicator->load('formula');
        }

        $tokens = $indicator->formula?->expression;

        if ($tokens === null || $tokens === []) {
            return [
                'kind' => self::KIND_MISSING,
                'message' => 'Sin fórmula de cálculo definida.',
            ];
        }

        $definedKeys = $indicator->dataFields->pluck('variable_key')->all();
        $result = app(MetFormulaValidationService::class)->validate($tokens, $definedKeys);

        if (! ($result['ok'] ?? false)) {
            $phase = $result['phase'] ?? MetFormulaValidationService::PHASE_STRUCTURE;

            return [
                'kind' => $phase === MetFormulaValidationService::PHASE_REFERENCES
                    ? self::KIND_UNKNOWN_VARIABLES
                    : self::KIND_INVALID,
                'message' => $result['message'] ?? 'La fórmula no es válida.',
            ];
        }

        return null;
    }

    /**
     * @param  Collection<int, MetIndicator>|iterable<int, MetIndicator>  $indicators
     * @return Collection<int, array{kind: string, message: string}>
     */
    public function issuesByIndicatorId(Collection|iterable $indicators): Collection
    {
        $issues = collect();

        foreach ($indicators as $indicator) {
            $issue = $this->issueForIndicator($indicator);
            if ($issue !== null) {
                $issues->put((int) $indicator->id, $issue);
            }
        }

        return $issues;
    }

    /**
     * @param  Collection<int, MetIndicator>  $indicators
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    public function dashboardAlertsForIndicators(Collection $indicators): array
    {
        if ($indicators->isEmpty()) {
            return [];
        }

        $alerts = [];

        foreach ($indicators as $indicator) {
            $issue = $this->issueForIndicator($indicator);
            if ($issue === null) {
                continue;
            }

            $type = $indicator->indicatorType;
            if ($type === null) {
                continue;
            }

            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Metas: '.$indicator->clave,
                'body' => 'Fórmula incompleta — '.$issue['message'],
                'url' => route('metas.instrument.indicators.show', [
                    'indicatorType' => $type,
                    'indicator' => $indicator,
                ]),
            ];
        }

        return $alerts;
    }
}
