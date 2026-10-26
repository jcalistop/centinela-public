<?php

/**
 * SDD-240 / SDD-241: export allow-list fixtures from minsal_centinela_ref_prod.
 * Incluye COMGES+CEN+plataforma (240) y catálogo Metas maestra (241).
 *
 * Usage: php database/seeders/bin/export-usable-catalog-from-ref-prod.php
 *
 * Env overrides: REF_PROD_DB, REF_PROD_HOST, REF_PROD_USER, REF_PROD_PASS
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$outDir = $root.'/database/seeders/fixtures/usable-catalog';

$host = getenv('REF_PROD_HOST') ?: '127.0.0.1';
$db = getenv('REF_PROD_DB') ?: 'minsal_centinela_ref_prod';
$user = getenv('REF_PROD_USER') ?: 'root';
$pass = getenv('REF_PROD_PASS') !== false ? (string) getenv('REF_PROD_PASS') : '';

/** @var list<string> $loadOrder */
$loadOrder = [
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
    'indicator_ambitos',
    'cen_regions',
    'cen_provinces',
    'cen_communes',
    'cen_hie_dependencies',
    'cen_hie_dependencies_regions',
    'cen_centers',
    'cen_addresses',
    'cen_catalog_glosa_equivalences',
    'com_commitments',
    'com_indicators',
    'com_indicator_cutoffs',
    'com_actions',
    'com_sensitivity_tables',
    'indicator_ambito_assignments',
    'github_release_snapshots',
    // SDD-241 Metas maestra (sin met_measurement_units: operación por centro)
    'met_unit_types',
    'met_indicators',
    'met_data_fields',
    'met_formulas',
    'indicator_type_global_settings',
];

$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

function fixCorruptJsonNewlines(?string $value): ?string
{
    if ($value === null || $value === '') {
        return $value;
    }

    if (is_array(json_decode($value, true))) {
        return $value;
    }

    $candidate = str_replace(['{rn', ',rn', 'rn}'], ["{\n", ",\n", "\n}"], $value);

    return is_array(json_decode($candidate, true)) ? $candidate : $value;
}

/**
 * @param  array<string, mixed>  $row
 * @return array<string, mixed>
 */
function sanitizeRow(string $table, array $row): array
{
    if ($table === 'cen_catalog_glosa_equivalences') {
        $row['reviewed_by_user_id'] = null;
        $row['source_sync_run_issue_id'] = null;
    }

    foreach (['descripcion', 'valores'] as $jsonCol) {
        if (isset($row[$jsonCol]) && is_string($row[$jsonCol])) {
            $row[$jsonCol] = fixCorruptJsonNewlines($row[$jsonCol]);
        }
    }

    return $row;
}

$counts = [];
$jsonFixes = 0;

foreach ($loadOrder as $table) {
    $exists = $pdo->query(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = '.$pdo->quote($db).' AND TABLE_NAME = '.$pdo->quote($table)
    )->fetchColumn();

    if ((int) $exists === 0) {
        fwrite(STDERR, "Missing table in ref_prod: {$table}\n");
        exit(1);
    }

    $orderCol = match ($table) {
        'permission_role' => 'permission_id, role_id',
        'cen_hie_dependencies_regions' => 'cen_hie_dependency_id, cen_region_id',
        default => 'id',
    };
    $rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY {$orderCol}")->fetchAll();
    $sanitized = [];

    foreach ($rows as $row) {
        $before = $row['descripcion'] ?? null;
        $row = sanitizeRow($table, $row);
        if (is_string($before) && ($row['descripcion'] ?? null) !== $before) {
            $jsonFixes++;
        }
        $sanitized[] = $row;
    }

    $path = $outDir.'/'.$table.'.json';
    $json = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "JSON encode failed for {$table}\n");
        exit(1);
    }
    file_put_contents($path, $json);
    $counts[$table] = count($sanitized);
    echo "{$table}: {$counts[$table]}\n";
}

$manifest = [
    'source' => 'minsal_centinela_ref_prod (SDD-240+241; allow-list 2026-10-06)',
    'generated_at' => gmdate('c'),
    'ref_database' => $db,
    'load_order' => $loadOrder,
    'row_counts' => $counts,
    'notes' => [
        'COMGES histórico 2022–2026 incluido',
        'indicator_ambitos + assignments compartidos (240+241)',
        'Metas maestra: met_unit_types, met_indicators, met_data_fields, met_formulas, indicator_type_global_settings',
        'met_measurement_units no se exporta (por cen_center_id; deny operación)',
        'cen_catalog_glosa_equivalences: FK user/sync nullificados',
        'descripcion JSON: reparación rn→newline si aplica',
    ],
];

file_put_contents(
    $outDir.'/manifest.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
);

echo "json_descripcion_fixes={$jsonFixes}\n";
echo "manifest written → {$outDir}/manifest.json\n";
