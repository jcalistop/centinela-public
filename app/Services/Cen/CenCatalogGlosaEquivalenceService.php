<?php

namespace App\Services\Cen;

use App\Enums\Cen\CenCatalogGlosaRuleKind;
use App\Models\CenCatalogGlosaEquivalence;
use App\Models\CenCenter;
use App\Models\CenCenterSyncRunIssue;
use App\Models\User;
use App\Support\Cen\CenCatalogGlosaResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class CenCatalogGlosaEquivalenceService
{
    public const CACHE_KEY = 'cen_catalog_glosa_equivalences:active:v1';

    public const CACHE_SECONDS = 3600;

    public function register(
        string $fieldKey,
        string $sourceGlosa,
        string $targetValue,
        User $reviewer,
        CenCatalogGlosaRuleKind $ruleKind = CenCatalogGlosaRuleKind::Alias,
        ?int $sourceSyncRunIssueId = null,
    ): CenCatalogGlosaEquivalence {
        $enumClass = CenCatalogGlosaResolver::enumClassForField($fieldKey);

        if ($enumClass::tryFrom($targetValue) === null) {
            throw ValidationException::withMessages([
                'target_value' => "El valor «{$targetValue}» no es válido para el campo {$fieldKey}.",
            ]);
        }

        $normalizedKey = CenCatalogGlosaResolver::normalizedEquivalenceLookupKey($fieldKey, $sourceGlosa);

        $equivalence = CenCatalogGlosaEquivalence::query()->updateOrCreate(
            [
                'field_key' => $fieldKey,
                'source_glosa_normalized' => $normalizedKey,
            ],
            [
                'source_glosa' => trim($sourceGlosa),
                'target_value' => $targetValue,
                'rule_kind' => $ruleKind,
                'active' => true,
                'reviewed_by_user_id' => $reviewer->id,
                'source_sync_run_issue_id' => $sourceSyncRunIssueId,
            ],
        );

        $this->forgetCache();
        $this->markMatchingUnmappedIssuesPromoted($fieldKey, $sourceGlosa, $equivalence->id);

        return $equivalence;
    }

    public function registerFromSyncIssue(CenCenterSyncRunIssue $issue, string $targetValue, User $reviewer): CenCatalogGlosaEquivalence
    {
        if ($issue->code !== 'unmapped_enum') {
            throw ValidationException::withMessages([
                'issue' => 'Solo se pueden promover incidencias con código unmapped_enum.',
            ]);
        }

        /** @var array<string, mixed>|null $context */
        $context = $issue->context;
        $fieldKey = is_string($context['field'] ?? null) ? $context['field'] : null;
        $apiGlosa = is_string($context['api_glosa'] ?? null) ? $context['api_glosa'] : null;

        if ($fieldKey === null || $apiGlosa === null || $apiGlosa === '') {
            throw ValidationException::withMessages([
                'issue' => 'La incidencia no incluye field/api_glosa en context; no se puede promover.',
            ]);
        }

        if (! in_array($fieldKey, CenCatalogGlosaResolver::fieldKeys(), true)) {
            throw ValidationException::withMessages([
                'field' => "Campo de catálogo desconocido: {$fieldKey}.",
            ]);
        }

        return $this->register(
            $fieldKey,
            $apiGlosa,
            $targetValue,
            $reviewer,
            CenCatalogGlosaRuleKind::Alias,
            $issue->id,
        );
    }

    public function deactivate(CenCatalogGlosaEquivalence $equivalence): void
    {
        $equivalence->update(['active' => false]);
        $this->forgetCache();
    }

    /**
     * @return LengthAwarePaginator<CenCatalogGlosaEquivalence>
     */
    public function paginateActive(int $perPage = 25, string $pageName = 'equiv_page'): LengthAwarePaginator
    {
        return CenCatalogGlosaEquivalence::query()
            ->where('active', true)
            ->with('reviewedBy')
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * @return Collection<int, CenCatalogGlosaEquivalence>
     */
    public function activeOrderedForExport(): Collection
    {
        return CenCatalogGlosaEquivalence::query()
            ->where('active', true)
            ->with('reviewedBy')
            ->orderBy('field_key')
            ->orderBy('source_glosa')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<CenCenterSyncRunIssue>
     */
    public function paginatePromotableUnmappedIssues(int $perPage = 20): LengthAwarePaginator
    {
        $paginator = CenCenterSyncRunIssue::query()
            ->where('code', 'unmapped_enum')
            ->whereNotNull('context')
            ->whereNull('promoted_at')
            ->with('syncRun')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'unmapped_page');

        $codigos = $paginator->getCollection()
            ->pluck('establecimiento_codigo')
            ->filter(fn (?string $codigo): bool => is_string($codigo) && $codigo !== '')
            ->unique()
            ->values();

        /** @var array<string, string> $namesByCodigo */
        $namesByCodigo = $codigos->isEmpty()
            ? []
            : CenCenter::withTrashed()
                ->whereIn('codigo', $codigos)
                ->pluck('nombre_oficial', 'codigo')
                ->all();

        $paginator->getCollection()->transform(function (CenCenterSyncRunIssue $issue) use ($namesByCodigo): CenCenterSyncRunIssue {
            $issue->setAttribute(
                'establecimiento_nombre_display',
                $this->resolveEstablishmentNameForIssue($issue, $namesByCodigo),
            );

            return $issue;
        });

        return $paginator;
    }

    /**
     * @param  array<string, string>  $namesByCodigo
     */
    private function resolveEstablishmentNameForIssue(CenCenterSyncRunIssue $issue, array $namesByCodigo): ?string
    {
        /** @var array<string, mixed>|null $context */
        $context = $issue->context;
        $fromContext = is_array($context) && is_string($context['establecimiento_nombre'] ?? null)
            ? $context['establecimiento_nombre']
            : null;

        if ($fromContext !== null && $fromContext !== '') {
            return $fromContext;
        }

        $codigo = $issue->establecimiento_codigo;

        if (! is_string($codigo) || $codigo === '') {
            return null;
        }

        return $namesByCodigo[$codigo] ?? null;
    }

    private function markMatchingUnmappedIssuesPromoted(
        string $fieldKey,
        string $sourceGlosa,
        int $equivalenceId,
    ): void {
        if (! Schema::hasColumn('cen_center_sync_run_issues', 'promoted_at')) {
            return;
        }

        $normalizedKey = CenCatalogGlosaResolver::normalizedEquivalenceLookupKey($fieldKey, $sourceGlosa);

        $issues = CenCenterSyncRunIssue::query()
            ->where('code', 'unmapped_enum')
            ->whereNull('promoted_at')
            ->where('context->field', $fieldKey)
            ->get()
            ->filter(fn (CenCenterSyncRunIssue $issue): bool => $this->issueContextMatchesGlosa(
                $issue,
                $fieldKey,
                $normalizedKey,
            ));

        foreach ($issues as $issue) {
            /** @var array<string, mixed> $context */
            $context = is_array($issue->context) ? $issue->context : [];
            $context['promoted_equivalence_id'] = $equivalenceId;

            $issue->update([
                'promoted_at' => now(),
                'context' => $context,
            ]);
        }
    }

    private function issueContextMatchesGlosa(
        CenCenterSyncRunIssue $issue,
        string $fieldKey,
        string $normalizedGlosaKey,
    ): bool {
        /** @var array<string, mixed>|null $context */
        $context = $issue->context;

        if (! is_array($context) || ($context['field'] ?? null) !== $fieldKey) {
            return false;
        }

        $apiGlosa = is_string($context['api_glosa'] ?? null) ? $context['api_glosa'] : '';

        return $apiGlosa !== ''
            && CenCatalogGlosaResolver::normalizedEquivalenceLookupKey($fieldKey, $apiGlosa) === $normalizedGlosaKey;
    }

    /**
     * @return array<string, array{targets: array<string, string>, rules: array<string, string>}>
     */
    public function activeDatabaseLookups(): array
    {
        if (! Schema::hasTable('cen_catalog_glosa_equivalences')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array {
            $built = [];

            $rows = CenCatalogGlosaEquivalence::query()
                ->where('active', true)
                ->orderBy('id')
                ->get(['field_key', 'target_value', 'source_glosa_normalized', 'rule_kind']);

            foreach ($rows as $row) {
                $fieldKey = $row->field_key;
                $key = $row->source_glosa_normalized;

                if (! isset($built[$fieldKey])) {
                    $built[$fieldKey] = ['targets' => [], 'rules' => []];
                }

                $built[$fieldKey]['targets'][$key] = $row->target_value;
                $built[$fieldKey]['rules'][$key] = $row->rule_kind->value.':'.$row->target_value;
            }

            return $built;
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        CenCatalogGlosaResolver::resetCacheForTesting();
    }
}
