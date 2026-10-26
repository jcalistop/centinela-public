<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CentinelaRouteAuditCommand extends Command
{
    protected $signature = 'centinela:route-audit
                            {--write-inventory : Escribe inventario en .github/docs/sdd/audits/route_inventory/YYYY-MM-DD_route-inventory.json (mismo formato que `php artisan route:list --json`).}
                            {--inventory-out= : Ruta explícita del JSON (p. ej. tests); anula la ruta SDD fechada.}
                            {--markdown : Imprime tabla markdown en stdout}
                            {--fail-on-high-gaps : Código de salida 1 si alguna ruta de riesgo alto carece de evidencia en tests/}';

    protected $description = 'Auditoría de rutas: inventario (route:list JSON) y análisis de huecos frente a tests/';

    public function handle(): int
    {
        $inventoryOut = $this->option('inventory-out');
        if (is_string($inventoryOut) && $inventoryOut !== '') {
            $this->writeRouteListJson($inventoryOut);
        } elseif ($this->option('write-inventory')) {
            $this->writeRouteListJson($this->sddDatedRouteInventoryPath());
        }

        $testBlob = $this->aggregateTestSource();
        $rows = $this->analyzeGaps($testBlob);

        $highGaps = 0;
        foreach ($rows as $row) {
            if ($row['risk'] === 'high' && ! $row['evidence'] && $row['status'] === 'gap') {
                $highGaps++;
            }
        }

        if ($this->option('markdown')) {
            $this->line($this->markdownTable($rows));
        } else {
            $this->table(
                ['Métodos', 'URI', 'Nombre', 'Riesgo', 'Evidencia en tests', 'Estado'],
                $this->tableRows($rows)
            );
        }

        $this->newLine();
        $this->line('Total rutas analizadas: '.count($rows));
        $this->line('Huecos (sin evidencia heurística): '.count(array_filter($rows, fn (array $r): bool => $r['status'] === 'gap')));
        $this->line('Huecos riesgo alto: '.$highGaps);

        if ($this->option('fail-on-high-gaps') && $highGaps > 0) {
            $this->error('Hay rutas de riesgo alto sin evidencia en tests/. Añadir pruebas o ajustar config/route_audit.php con criterio documentado.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function writeRouteListJson(string $path): void
    {
        Artisan::call('route:list', ['--json' => true]);
        $json = Artisan::output();
        $fullPath = $this->absoluteInventoryPath($path);
        $directory = dirname($fullPath);
        if ($directory !== '' && $directory !== '.') {
            File::ensureDirectoryExists($directory);
        }
        File::put($fullPath, $json);
        $this->info('Inventario escrito: '.$fullPath.' ('.strlen($json).' bytes)');
    }

    private function absoluteInventoryPath(string $path): string
    {
        if (str_starts_with($path, '/') || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':')) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Convención SDD: un archivo por fecha bajo `audits/route_inventory/`.
     */
    private function sddDatedRouteInventoryPath(): string
    {
        return base_path('.github/docs/sdd/audits/route_inventory/'.now()->format('Y-m-d').'_route-inventory.json');
    }

    private function aggregateTestSource(): string
    {
        $buffer = '';
        foreach (File::allFiles(base_path('tests')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $buffer .= "\n".$file->getContents();
        }

        return $buffer;
    }

    /**
     * @return list<array{methods: string, uri: string, name: string, risk: string, evidence: bool, status: string}>
     */
    private function analyzeGaps(string $testBlob): array
    {
        $rows = [];
        foreach (app('router')->getRoutes() as $route) {
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            if ($methods === []) {
                continue;
            }
            $uri = $route->uri();
            $name = $route->getName();

            if ($this->shouldExcludeFromGapReport($name, $uri)) {
                continue;
            }

            $risk = $this->classifyRisk($name, $uri);
            $evidence = $this->hasTestEvidence($testBlob, $name, $uri);
            $status = $evidence ? 'cubierta' : 'gap';

            $rows[] = [
                'methods' => implode('|', $methods),
                'uri' => $uri,
                'name' => $name ?? '—',
                'risk' => $risk,
                'evidence' => $evidence,
                'status' => $status,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['risk'] !== $b['risk']) {
                $order = ['high' => 0, 'medium' => 1, 'low' => 2];

                return ($order[$a['risk']] ?? 3) <=> ($order[$b['risk']] ?? 3);
            }
            if ($a['status'] !== $b['status']) {
                return $a['status'] === 'gap' ? -1 : 1;
            }

            return strcmp($a['uri'], $b['uri']);
        });

        return $rows;
    }

    private function shouldExcludeFromGapReport(?string $name, string $uri): bool
    {
        foreach (config('route_audit.exclude_uri_contains', []) as $fragment) {
            if (Str::contains($uri, $fragment)) {
                return true;
            }
        }
        if ($name !== null) {
            foreach (config('route_audit.exclude_route_name_prefixes', []) as $prefix) {
                if (Str::startsWith($name, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function classifyRisk(?string $name, string $uri): string
    {
        foreach (config('route_audit.risk_high_uri_prefixes', []) as $prefix) {
            if (Str::startsWith($uri, $prefix)) {
                return 'high';
            }
        }
        if ($name !== null) {
            foreach (config('route_audit.risk_high_route_name_prefixes', []) as $prefix) {
                if (Str::startsWith($name, $prefix) || $name === $prefix) {
                    return 'high';
                }
            }
            foreach (
                array_merge(
                    config('route_audit.risk_medium_route_name_prefixes', []),
                    config('route_audit.risk_medium_route_name_extra_prefixes', [])
                ) as $prefix
            ) {
                if (Str::startsWith($name, $prefix)) {
                    return 'medium';
                }
            }
        }

        return 'low';
    }

    private function hasTestEvidence(string $testBlob, ?string $name, string $uri): bool
    {
        if ($name !== null && $name !== '') {
            $pattern = '/route\s*\(\s*[\'"]'.preg_quote($name, '/').'[\'"]/';
            if (preg_match($pattern, $testBlob) === 1) {
                return true;
            }
            $firstSegment = explode('.', $name, 2)[0];
            foreach (config('route_audit.module_evidence_first_segments', []) as $segment) {
                if ($firstSegment === $segment) {
                    $segmentPattern = '/route\s*\(\s*[\'"]'.preg_quote($segment, '/').'\./';
                    if (preg_match($segmentPattern, $testBlob) === 1) {
                        return true;
                    }
                }
            }
        }

        return $this->hasPathEvidence($testBlob, $uri);
    }

    private function hasPathEvidence(string $testBlob, string $uri): bool
    {
        $staticPath = $this->staticUriPrefixForSearch($uri);
        if ($staticPath === null || strlen($staticPath) < 4) {
            return false;
        }

        $candidates = [
            "'".$staticPath."'",
            '"'.$staticPath.'"',
            "'".$staticPath.'/',
            '"'.$staticPath.'/',
        ];
        foreach ($candidates as $needle) {
            if (str_contains($testBlob, $needle)) {
                return true;
            }
        }

        if (strlen($staticPath) >= 12) {
            if (str_contains($testBlob, "'".$staticPath) || str_contains($testBlob, '"'.$staticPath)) {
                return true;
            }
        }

        return false;
    }

    private function staticUriPrefixForSearch(string $uri): ?string
    {
        $brace = strpos($uri, '{');
        $prefix = $brace === false ? $uri : substr($uri, 0, $brace);
        $prefix = trim($prefix, '/');
        if ($prefix === '') {
            return null;
        }

        return '/'.$prefix;
    }

    /**
     * @param  list<array{methods: string, uri: string, name: string, risk: string, evidence: bool, status: string}>  $rows
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    private function tableRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                $row['methods'],
                $row['uri'],
                $row['name'],
                $row['risk'],
                $row['evidence'] ? 'sí' : 'no',
                $row['status'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{methods: string, uri: string, name: string, risk: string, evidence: bool, status: string}>  $rows
     */
    private function markdownTable(array $rows): string
    {
        $lines = ['| Métodos | URI | Nombre | Riesgo | Evidencia | Estado |', '| --- | --- | --- | --- | --- | --- |'];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %s |',
                str_replace('|', '\\|', $row['methods']),
                str_replace('|', '\\|', $row['uri']),
                str_replace('|', '\\|', $row['name']),
                $row['risk'],
                $row['evidence'] ? 'sí' : 'no',
                $row['status']
            );
        }

        return implode("\n", $lines);
    }
}
