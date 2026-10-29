<?php

declare(strict_types=1);

namespace App\Services\Github;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubCiStatusService
{
    private const CACHE_KEY = 'github.ci:latest_main_run:v1';

    private const CACHE_TTL_SECONDS = 120;

    public function latestForDeployBranch(): GithubCiStatus
    {
        $branch = (string) config('centinela.deploy.branch', 'main');
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof GithubCiStatus && ! $cached->stale) {
            return $cached;
        }

        $fetched = $this->fetchLatestRun($branch);
        if ($fetched !== null) {
            Cache::put(self::CACHE_KEY, $fetched, self::CACHE_TTL_SECONDS);

            return $fetched;
        }

        if ($cached instanceof GithubCiStatus) {
            return new GithubCiStatus(
                state: $cached->state,
                conclusion: $cached->conclusion,
                status: $cached->status,
                workflowName: $cached->workflowName,
                headSha: $cached->headSha,
                headShaShort: $cached->headShaShort,
                htmlUrl: $cached->htmlUrl,
                runAt: $cached->runAt,
                stale: true,
                errorMessage: 'No se pudo actualizar el estado de CI; mostrando último valor en caché.',
            );
        }

        return new GithubCiStatus(
            state: 'unknown',
            conclusion: null,
            status: null,
            workflowName: null,
            headSha: null,
            headShaShort: null,
            htmlUrl: null,
            runAt: null,
            stale: false,
            errorMessage: 'CI de GitHub no disponible (token, red o API).',
        );
    }

    /**
     * Motivo de bloqueo del gate CI (SDD-100), o null si el deploy puede continuar.
     *
     * @param  'auto'|'manual'  $deployMode
     */
    public function deployGateBlockReason(string $deployMode, ?string $remoteSha = null): ?string
    {
        $gateMode = $this->normalizedRequireCiMode();

        if ($gateMode === 'none') {
            return null;
        }

        if ($gateMode === 'auto' && $deployMode === 'manual') {
            return null;
        }

        $status = $this->latestForDeployBranch();

        if ($status->state === 'success') {
            if (! (bool) config('centinela.deploy.require_ci_match_sha', false)) {
                return null;
            }

            $expected = is_string($remoteSha) ? strtolower(trim($remoteSha)) : '';
            $actual = is_string($status->headSha) ? strtolower(trim($status->headSha)) : '';

            if ($expected === '' || $actual === '') {
                return 'Gate CI: no se pudo comparar el SHA del run con origin/'.config('centinela.deploy.branch', 'main').'.';
            }

            if (! hash_equals($expected, $actual)) {
                $shortExpected = substr($expected, 0, 7);
                $shortActual = substr($actual, 0, 7);

                return "Gate CI: el último run exitoso ({$shortActual}) no coincide con el SHA remoto a desplegar ({$shortExpected}).";
            }

            return null;
        }

        if ($status->state === 'unknown') {
            if (! (bool) config('centinela.deploy.require_ci_block_unknown', false)) {
                return null;
            }

            $detail = is_string($status->errorMessage) && $status->errorMessage !== ''
                ? $status->errorMessage
                : 'estado desconocido';

            return 'Gate CI estricto: no se pudo verificar CI ('.$detail.').';
        }

        if ($status->state === 'pending') {
            return 'Gate CI: el último workflow en la rama de deploy aún está en curso (pending).';
        }

        $conclusion = $status->conclusion ?? $status->state;

        return 'Gate CI: el último workflow en la rama de deploy no está en success ('.$conclusion.').';
    }

    /**
     * @return 'none'|'auto'|'all'
     */
    public function normalizedRequireCiMode(): string
    {
        $mode = config('centinela.deploy.require_ci_mode', 'none');

        if ($mode === false || $mode === null || $mode === '' || $mode === 'Off' || $mode === 'off') {
            return 'none';
        }

        if ($mode === true || $mode === 'On' || $mode === 'on') {
            return 'all';
        }

        $normalized = strtolower(trim((string) $mode));

        return in_array($normalized, ['none', 'auto', 'all'], true) ? $normalized : 'none';
    }

    private function fetchLatestRun(string $branch): ?GithubCiStatus
    {
        $repository = (string) config('services.github.releases_repository', 'jcalistop/centinela-public');
        $url = "https://api.github.com/repos/{$repository}/actions/runs";

        try {
            $client = Http::withoutVerifying()
                ->timeout(12)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent' => (string) config('app.name', 'Laravel'),
                ]);

            $accessToken = config('services.github.releases_token');
            if (is_string($accessToken) && $accessToken !== '') {
                $client = $client->withToken($accessToken);
            }

            $response = $client->get($url, [
                'branch' => $branch,
                'per_page' => 1,
                'event' => 'push',
            ]);

            if (! $response->successful()) {
                Log::warning('GitHub Actions runs request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 200),
                ]);

                return null;
            }

            $runs = $response->json('workflow_runs');
            if (! is_array($runs) || $runs === [] || ! is_array($runs[0])) {
                return new GithubCiStatus(
                    state: 'unknown',
                    conclusion: null,
                    status: null,
                    workflowName: null,
                    headSha: null,
                    headShaShort: null,
                    htmlUrl: null,
                    runAt: null,
                    stale: false,
                    errorMessage: 'Sin ejecuciones de Actions para la rama '.$branch.'.',
                );
            }

            return $this->mapRun($runs[0]);
        } catch (\Throwable $e) {
            Log::warning('GitHub Actions runs request exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function mapRun(array $run): GithubCiStatus
    {
        $status = is_string($run['status'] ?? null) ? (string) $run['status'] : null;
        $conclusion = is_string($run['conclusion'] ?? null) ? (string) $run['conclusion'] : null;
        $headSha = is_string($run['head_sha'] ?? null) ? (string) $run['head_sha'] : null;
        $htmlUrl = is_string($run['html_url'] ?? null) ? (string) $run['html_url'] : null;
        $name = is_string($run['name'] ?? null) ? (string) $run['name'] : null;
        $createdAt = is_string($run['created_at'] ?? null) ? CarbonImmutable::parse($run['created_at']) : null;

        $state = match (true) {
            $status === 'completed' && $conclusion === 'success' => 'success',
            $status === 'completed' && in_array($conclusion, ['failure', 'cancelled', 'timed_out', 'action_required'], true) => 'failure',
            in_array($status, ['queued', 'in_progress', 'waiting', 'requested', 'pending'], true) => 'pending',
            default => 'unknown',
        };

        return new GithubCiStatus(
            state: $state,
            conclusion: $conclusion,
            status: $status,
            workflowName: $name,
            headSha: $headSha !== null && $headSha !== '' ? $headSha : null,
            headShaShort: $headSha !== null && $headSha !== '' ? substr($headSha, 0, 7) : null,
            htmlUrl: $htmlUrl,
            runAt: $createdAt,
            stale: false,
        );
    }
}
