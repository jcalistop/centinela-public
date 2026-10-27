<?php

/**
 * SDD-104: Verifica que los paquetes laravel-lang en composer.lock no apunten
 * a commits maliciosos del incidente de supply chain (mayo 2026).
 *
 * Uso: php scripts/verify-laravel-lang-lock.php [--lock=path] [--skip-api] [--json]
 * Exit 0 = OK, 1 = hallazgo crítico, 2 = error de ejecución.
 */

declare(strict_types=1);

const ATTACK_WINDOW_START = '2026-05-22T22:32:00+00:00';

/** @var array<string, string> package => repo slug on GitHub */
const PACKAGE_REPOS = [
    'laravel-lang/lang' => 'lang',
    'laravel-lang/http-statuses' => 'http-statuses',
    'laravel-lang/attributes' => 'attributes',
    'laravel-lang/actions' => 'actions',
];

/** @var array<string, string> known malicious commit SHAs (StepSecurity / Snyk, mayo 2026) */
const KNOWN_MALICIOUS_SHAS = [
    'a5ea2e8fa92ccf29cdb1d2dadbeb27722b2bff37',
    '50ac0db454d19234c835716f297bbc5363c0a25c',
    'c45764e70285146da37025cd8601a921ab8a7eda',
    'a9f8d88cf98e35988d3d0fd6d79547f980853041',
    'bba2e443dc7ff1f8704f52a5375383e3f4f643b8',
    '26c233e1a0d4fd2331e8e0f175e18f8eed904aa3',
    'db0c3ef246103fd0f6c318e0d48f26b5289044c3',
    '9ee599d248cc322fa26054694a83a1f4558cc716',
    '6b1d5782a8c8c199d070857802d39bfe609eb6f2',
    '556d2b335d4d6d92139822017ee461b668afe375',
    '722cee67326d932e7f71ba3438f62a255d779aa9',
    'ad24b980db8f0dca50ccb3ba6badb3c2331e0ef4',
    'd59561727927117e65b35f0183cae131baad19fe',
    '1713b19cbf609cb101ff5e216be41f7224269082',
    'daa5212264bb73fb39fe7a36618b62717dc564a5',
];

/** @var array<int, string> */
const MALICIOUS_AUTHOR_NAMES = ['Your Name'];

/** @var array<int, string> */
const MALICIOUS_AUTHOR_EMAILS = ['you@example.com'];

$options = getopt('', ['lock:', 'skip-api', 'json']);
$lockPath = $options['lock'] ?? (__DIR__.'/../composer.lock');
$skipApi = array_key_exists('skip-api', $options);
$jsonOnly = array_key_exists('json', $options);

if (! is_string($lockPath) || ! is_file($lockPath)) {
    fwrite(STDERR, "composer.lock no encontrado: {$lockPath}\n");

    exit(2);
}

$raw = file_get_contents($lockPath);
if ($raw === false) {
    fwrite(STDERR, "No se pudo leer composer.lock\n");

    exit(2);
}

/** @var array<string, mixed>|null $lock */
$lock = json_decode($raw, true);
if (! is_array($lock) || ! isset($lock['packages']) || ! is_array($lock['packages'])) {
    fwrite(STDERR, "composer.lock inválido\n");

    exit(2);
}

/** @var list<array<string, mixed>> $allPackages */
$allPackages = [];

foreach (['packages', 'packages-dev'] as $section) {
    if (! isset($lock[$section]) || ! is_array($lock[$section])) {
        continue;
    }

    foreach ($lock[$section] as $package) {
        if (is_array($package)) {
            $allPackages[] = $package;
        }
    }
}

/** @var array<string, array{version: string, reference: string}> $found */
$found = [];

foreach ($allPackages as $package) {
    $name = $package['name'] ?? null;
    if (! is_string($name) || ! array_key_exists($name, PACKAGE_REPOS)) {
        continue;
    }

    $reference = $package['source']['reference'] ?? null;
    if (! is_string($reference) || $reference === '') {
        continue;
    }

    $found[$name] = [
        'version' => is_string($package['version'] ?? null) ? $package['version'] : 'unknown',
        'reference' => strtolower($reference),
    ];
}

/** @var list<array<string, mixed>> $issues */
$issues = [];

/** @var list<array<string, mixed>> $packagesReport */
$packagesReport = [];

foreach (PACKAGE_REPOS as $packageName => $repoSlug) {
    if (! isset($found[$packageName])) {
        $packagesReport[] = [
            'package' => $packageName,
            'status' => 'not_in_lock',
            'message' => 'Paquete no presente en composer.lock (puede ser OK si no hay dependencia dev)',
        ];

        continue;
    }

    $entry = $found[$packageName];
    $sha = $entry['reference'];
    $packageIssues = [];

    if (in_array($sha, KNOWN_MALICIOUS_SHAS, true)) {
        $packageIssues[] = [
            'code' => 'known_malicious_sha',
            'message' => "SHA coincide con commit malicioso publicado (StepSecurity/Snyk): {$sha}",
        ];
    }

    $commitMeta = null;
    if (! $skipApi) {
        $commitMeta = fetchCommitMeta($repoSlug, $sha);
        if ($commitMeta === null) {
            $packageIssues[] = [
                'code' => 'api_lookup_failed',
                'message' => 'No se pudo verificar el commit en GitHub API (revisar conectividad o SHA)',
            ];
        } else {
            $authorName = (string) ($commitMeta['author_name'] ?? '');
            $authorEmail = (string) ($commitMeta['author_email'] ?? '');
            $committedAt = (string) ($commitMeta['committed_at'] ?? '');

            if (
                in_array($authorName, MALICIOUS_AUTHOR_NAMES, true)
                || in_array(strtolower($authorEmail), MALICIOUS_AUTHOR_EMAILS, true)
            ) {
                $packageIssues[] = [
                    'code' => 'malicious_author',
                    'message' => "Autor sospechoso: {$authorName} <{$authorEmail}>",
                ];
            }

            if ($committedAt !== '') {
                $committedTs = strtotime($committedAt);
                $windowStartTs = strtotime(ATTACK_WINDOW_START);
                if (
                    $committedTs !== false && $windowStartTs !== false
                    && $committedTs >= $windowStartTs
                    && in_array($authorName, MALICIOUS_AUTHOR_NAMES, true)
                ) {
                    $packageIssues[] = [
                        'code' => 'attack_window_commit',
                        'message' => "Commit dentro de ventana de ataque con autor malicioso: {$committedAt}",
                    ];
                }
            }
        }
    }

    $status = $packageIssues === [] ? 'ok' : 'fail';

    $packagesReport[] = [
        'package' => $packageName,
        'version' => $entry['version'],
        'reference' => $sha,
        'status' => $status,
        'commit' => $commitMeta,
        'issues' => $packageIssues,
    ];

    foreach ($packageIssues as $issue) {
        if (($issue['code'] ?? '') === 'api_lookup_failed') {
            continue;
        }
        $issues[] = array_merge(['package' => $packageName], $issue);
    }
}

$presentCount = count(array_filter($packagesReport, fn (array $p): bool => ($p['status'] ?? '') !== 'not_in_lock'));
$ok = $issues === [];

$report = [
    'ok' => $ok,
    'lock_file' => realpath($lockPath) ?: $lockPath,
    'attack_window_start_utc' => ATTACK_WINDOW_START,
    'packages_checked' => $presentCount,
    'packages' => $packagesReport,
    'issues' => $issues,
    'verified_at' => gmdate('c'),
];

if ($jsonOnly) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} else {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

    if ($ok) {
        fwrite(STDOUT, "\nSDD-104: composer.lock laravel-lang — OK ({$presentCount} paquete(s) verificados)\n");
    } else {
        fwrite(STDERR, "\nSDD-104: composer.lock laravel-lang — FALLO (".count($issues)." hallazgo(s))\n");
    }
}

exit($ok ? 0 : 1);

/**
 * @return array{author_name: string, author_email: string, committed_at: string}|null
 */
function fetchCommitMeta(string $repoSlug, string $sha): ?array
{
    $url = 'https://api.github.com/repos/Laravel-Lang/'.$repoSlug.'/commits/'.urlencode($sha);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                'User-Agent: centinela-public-sdd-104',
                'Accept: application/vnd.github+json',
            ]),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    /** @var array<string, mixed>|null $data */
    $data = json_decode($response, true);
    if (! is_array($data) || ! isset($data['commit']) || ! is_array($data['commit'])) {
        return null;
    }

    $commit = $data['commit'];
    $author = is_array($commit['author'] ?? null) ? $commit['author'] : [];

    return [
        'author_name' => (string) ($author['name'] ?? ''),
        'author_email' => (string) ($author['email'] ?? ''),
        'committed_at' => (string) ($author['date'] ?? ''),
    ];
}
