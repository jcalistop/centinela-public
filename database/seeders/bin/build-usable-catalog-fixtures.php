<?php

/**
 * One-shot (SDD-240): parse legacy SQL → JSON fixtures aligned to current schema.
 *
 * Usage: php database/seeders/bin/build-usable-catalog-fixtures.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$source = $root.'/database/seeders/sql/2025-08-22_centinela_clean.sql';
$outDir = $root.'/database/seeders/fixtures/usable-catalog';

if (! is_file($source)) {
    fwrite(STDERR, "Source SQL missing: {$source}\n");
    exit(1);
}

if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

$sql = file_get_contents($source);
if ($sql === false) {
    fwrite(STDERR, "Cannot read source SQL\n");
    exit(1);
}

/**
 * @return array{0: list<string>, 1: list<list<mixed>>}
 */
function parseInserts(string $sql, string $table): array
{
    $needle = 'INSERT INTO `'.$table.'`';
    $columns = [];
    $rows = [];
    $offset = 0;

    while (($start = stripos($sql, $needle, $offset)) !== false) {
        $parenCols = strpos($sql, '(', $start + strlen($needle));
        if ($parenCols === false) {
            break;
        }
        $closeCols = strpos($sql, ')', $parenCols);
        if ($closeCols === false) {
            break;
        }

        $columns = array_map(
            static fn (string $c): string => trim($c, " `\t\n\r"),
            explode(',', substr($sql, $parenCols + 1, $closeCols - $parenCols - 1))
        );

        $valuesPos = stripos($sql, 'VALUES', $closeCols);
        if ($valuesPos === false) {
            break;
        }
        $i = $valuesPos + 6;
        $length = strlen($sql);
        $inQuote = false;

        // Advance to first row '('
        while ($i < $length && $sql[$i] !== '(') {
            $i++;
        }

        while ($i < $length) {
            while ($i < $length && (ctype_space($sql[$i]) || $sql[$i] === ',')) {
                $i++;
            }
            if ($i >= $length) {
                break;
            }
            if ($sql[$i] === ';') {
                $offset = $i + 1;
                break;
            }
            if ($sql[$i] !== '(') {
                $offset = $i;
                break;
            }
            $i++;
            $row = [];
            $current = '';
            $inQuote = false;

            while ($i < $length) {
                $ch = $sql[$i];

                if ($inQuote) {
                    if ($ch === '\\' && $i + 1 < $length) {
                        $current .= $sql[$i + 1];
                        $i += 2;

                        continue;
                    }
                    if ($ch === "'") {
                        if ($i + 1 < $length && $sql[$i + 1] === "'") {
                            $current .= "'";
                            $i += 2;

                            continue;
                        }
                        $inQuote = false;
                        $i++;

                        continue;
                    }
                    $current .= $ch;
                    $i++;

                    continue;
                }

                if ($ch === "'") {
                    $inQuote = true;
                    $i++;

                    continue;
                }

                if ($ch === ',') {
                    $row[] = normalizeSqlValue($current);
                    $current = '';
                    $i++;

                    continue;
                }

                if ($ch === ')') {
                    $row[] = normalizeSqlValue($current);
                    $i++;
                    break;
                }

                $current .= $ch;
                $i++;
            }

            if (count($row) === count($columns)) {
                $rows[] = $row;
            }
        }

        if ($offset <= $start) {
            $offset = $i;
        }
    }

    return [$columns, $rows];
}

function normalizeSqlValue(string $raw): mixed
{
    $value = trim($raw);
    if (strcasecmp($value, 'NULL') === 0) {
        return null;
    }

    return $value;
}

/**
 * @param  list<string>  $columns
 * @param  list<list<mixed>>  $rows
 * @return list<array<string, mixed>>
 */
function toAssoc(array $columns, array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $assoc = [];
        foreach ($columns as $idx => $col) {
            $assoc[$col] = $row[$idx] ?? null;
        }
        $out[] = $assoc;
    }

    return $out;
}

/**
 * @param  list<array<string, mixed>>  $rows
 */
function writeJson(string $path, array $rows): void
{
    $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON encode failed for '.$path);
    }
    if (file_put_contents($path, $json) === false) {
        throw new RuntimeException('Write failed: '.$path);
    }
    echo basename($path).': '.count($rows)." rows\n";
}

$lookupTables = [
    'cen_types' => 'nombre',
    'cen_scopes' => 'nombre',
    'cen_certifications' => 'nombre',
    'cen_adm_dependencies' => 'nombre',
    'cen_emergency_types' => 'nombre',
    'cen_provider_types' => 'nombre',
];

$lookups = [];
foreach ($lookupTables as $table => $nameCol) {
    [$cols, $rows] = parseInserts($sql, $table);
    $map = [];
    foreach (toAssoc($cols, $rows) as $row) {
        $map[(int) $row['id']] = $row[$nameCol] ?? null;
    }
    $lookups[$table] = $map;
}

$directTables = [
    'years',
    'indicator_types',
    'cutoffs',
    'cen_regions',
    'cen_provinces',
    'cen_communes',
    'cen_hie_dependencies',
    'cen_hie_dependencies_regions',
    'cen_addresses',
    'com_commitments',
    'com_indicators',
    'com_indicator_cutoffs',
    'com_actions',
    'com_sensitivity_tables',
    'menus',
    'menu_items',
    'data_types',
    'data_rows',
    'permissions',
    'permission_role',
    'roles',
    'settings',
];

foreach ($directTables as $table) {
    [$cols, $rows] = parseInserts($sql, $table);
    $assoc = toAssoc($cols, $rows);

    if ($table === 'cen_hie_dependencies') {
        $assoc = array_map(static function (array $row): array {
            $tipo = (string) ($row['tipo_dependencia'] ?? '');
            $row['habilitado'] = $tipo === 'SERVICIO' ? 1 : 0;

            return $row;
        }, $assoc);
    }

    if ($table === 'cutoffs') {
        $assoc = array_map(static function (array $row): array {
            $row['orden'] = $row['orden'] ?? (int) $row['id'];
            $row['habilitado'] = $row['habilitado'] ?? 1;

            return $row;
        }, $assoc);
    }

    if ($table === 'indicator_types') {
        // El SQL 2025-08-22 trae route legacy "metas". Canónico = migraciones 2026_04_*.
        $assoc = [
            [
                'id' => 1,
                'clave_indicador' => 'COMGES',
                'nombre_indicador' => 'Compromisos de Gestión',
                'descripcion_indicador' => 'Instrumento COMGES',
                'icon_class' => 'voyager-bar-chart',
                'route' => 'voyager.compromisos.index',
                'view' => null,
                'estado' => 1,
                'orden' => 1,
                'measurement_granularity' => 'establishment',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'clave_indicador' => 'Metas Ley 18.834',
                'nombre_indicador' => 'Metas Sanitarias Ley 18.834',
                'descripcion_indicador' => 'Instrumento Metas — Ley 18.834 (granularidad establecimiento).',
                'icon_class' => 'voyager-pie-chart',
                'route' => 'metas-18834',
                'view' => null,
                'estado' => 1,
                'orden' => 2,
                'measurement_granularity' => 'establishment',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 3,
                'clave_indicador' => 'Metas Ley 20.707',
                'nombre_indicador' => 'Metas Ley 20.707',
                'descripcion_indicador' => 'Instrumento Metas — Ley 20.707 (granularidad unidad interna).',
                'icon_class' => 'voyager-pie-graph',
                'route' => 'metas-20707',
                'view' => null,
                'estado' => 1,
                'orden' => 3,
                'measurement_granularity' => 'internal_unit',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 4,
                'clave_indicador' => 'Metas Ley 19.664',
                'nombre_indicador' => 'Metas Sanitarias Ley 19.664',
                'descripcion_indicador' => 'Instrumento Metas — Ley 19.664 (granularidad establecimiento).',
                'icon_class' => 'voyager-dashboard',
                'route' => 'metas-19664',
                'view' => null,
                'estado' => 1,
                'orden' => 4,
                'measurement_granularity' => 'establishment',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 5,
                'clave_indicador' => 'adp',
                'nombre_indicador' => 'Alta Dirección Pública (ADP)',
                'descripcion_indicador' => 'Instrumento ADP — evaluación por cargo en establecimiento (SDD-026).',
                'icon_class' => 'voyager-logbook',
                'route' => 'adp',
                'view' => null,
                'estado' => 0,
                'orden' => 5,
                'measurement_granularity' => 'adp_cargo_establishment',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
        ];
    }

    if ($table === 'com_indicators') {
        $assoc = array_map(static function (array $row): array {
            $row['orden'] = $row['orden'] ?? 0;

            return $row;
        }, $assoc);
    }

    writeJson($outDir.'/'.$table.'.json', $assoc);
}

[$centerCols, $centerRows] = parseInserts($sql, 'cen_centers');
$centers = [];
foreach (toAssoc($centerCols, $centerRows) as $row) {
    $centers[] = [
        'id' => $row['id'],
        'origen' => 'seed',
        'codigo' => $row['codigo'],
        'cod_antiguo' => $row['cod_antiguo'],
        'codigo_madre' => $row['codigo_madre'],
        'cod_madre_antiguo' => $row['cod_madre_antiguo'],
        'cen_hie_dependency_id' => $row['cen_hie_dependency_id'],
        'pertenencia_snss' => $row['pertenencia_snss'],
        'nombre_oficial' => $row['nombre_oficial'],
        'nombre_corto' => $row['nombre_corto'],
        'nivel_atencion' => $row['nivel_atencion'],
        'tiene_urgencia' => $row['tiene_urgencia'],
        'tipo_sapu' => $row['tipo_sapu'],
        'estado_funcionamiento' => $row['estado_funcionamiento'],
        'nivel_complejidad' => $row['nivel_complejidad'],
        'tipo_atencion' => $row['tipo_atencion'],
        'cen_type' => $lookups['cen_types'][(int) ($row['cen_type_id'] ?? 0)] ?? null,
        'cen_scope' => $lookups['cen_scopes'][(int) ($row['cen_scope_id'] ?? 0)] ?? null,
        'cen_certification' => $lookups['cen_certifications'][(int) ($row['cen_certification_id'] ?? 0)] ?? null,
        'cen_adm_dependency' => $lookups['cen_adm_dependencies'][(int) ($row['cen_adm_dependency_id'] ?? 0)] ?? null,
        'cen_emergency_type' => $lookups['cen_emergency_types'][(int) ($row['cen_emergency_type_id'] ?? 0)] ?? null,
        'cen_provider_type' => $lookups['cen_provider_types'][(int) ($row['cen_provider_type_id'] ?? 0)] ?? null,
        'synced_at' => null,
        'tarea_pendiente' => 0,
        'deleted_at' => null,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}
writeJson($outDir.'/cen_centers.json', $centers);

$manifest = [
    'source' => 'transformed from 2025-08-22_centinela_clean.sql (SDD-240)',
    'generated_at' => gmdate('c'),
    'load_order' => [
        'roles',
        'permissions',
        'permission_role',
        'years',
        'indicator_types',
        'cutoffs',
        'settings',
        'menus',
        'menu_items',
        'data_types',
        'data_rows',
        'cen_regions',
        'cen_provinces',
        'cen_communes',
        'cen_hie_dependencies',
        'cen_hie_dependencies_regions',
        'cen_centers',
        'cen_addresses',
        'com_commitments',
        'com_indicators',
        'com_indicator_cutoffs',
        'com_actions',
        'com_sensitivity_tables',
    ],
];
file_put_contents($outDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo "Done → {$outDir}\n";
