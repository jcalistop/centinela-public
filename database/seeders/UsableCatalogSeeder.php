<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * SDD-240 + SDD-241: catálogos COMGES + CEN + plataforma Voyager + Metas maestra (allow-list).
 * Fixtures: `fixtures/usable-catalog/` exportadas desde `minsal_centinela_ref_prod`
 * (`bin/export-usable-catalog-from-ref-prod.php`). Sin operación SS.
 *
 * Runbook: `composer run schema:fresh` luego `php artisan db:seed` (BD del `.env`).
 */
class UsableCatalogSeeder extends Seeder
{
    /** @var list<string> */
    private const METAS_CATALOG_TABLES = [
        'met_unit_types',
        'met_indicators',
        'met_data_fields',
        'met_formulas',
        'indicator_type_global_settings',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('⛔ BLOQUEADO: UsableCatalogSeeder no puede ejecutarse en production.');

            return;
        }

        $fixtureDir = $this->fixtureDirectory();
        $manifestPath = $fixtureDir.DIRECTORY_SEPARATOR.'manifest.json';

        if (! is_file($manifestPath)) {
            throw new RuntimeException("Manifest de fixtures no encontrado: {$manifestPath}");
        }

        /** @var array{load_order?: list<string>} $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $order = $manifest['load_order'] ?? [];

        if ($order === []) {
            throw new RuntimeException('manifest.json sin load_order');
        }

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($fixtureDir, $order): void {
                $metasAsserted = false;
                foreach ($order as $table) {
                    if (! $metasAsserted && in_array($table, self::METAS_CATALOG_TABLES, true)) {
                        $this->assertMetasPrerequisites();
                        $metasAsserted = true;
                    }
                    $this->seedTable($fixtureDir, $table);
                }
                $this->ensureDefaultYear();
            });
        } catch (Throwable $e) {
            throw new RuntimeException('UsableCatalogSeeder falló (fail-fast): '.$e->getMessage(), 0, $e);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        Cache::forget('indicator_type_routes');
        Cache::forget('default_year');

        $this->command?->info('UsableCatalogSeeder: allow-list cargada desde '.$fixtureDir);
    }

    /**
     * SDD-241: no insertar Metas huérfanas si faltan years / indicator_types.
     */
    private function assertMetasPrerequisites(): void
    {
        $years = Schema::hasTable('years') ? (int) DB::table('years')->count() : 0;
        $types = Schema::hasTable('indicator_types') ? (int) DB::table('indicator_types')->count() : 0;

        if ($years === 0 || $types === 0) {
            throw new RuntimeException(
                'Prerequisitos SDD-240 ausentes para Metas (years='.$years.', indicator_types='.$types.'). '
                    .'Asegura fixtures years e indicator_types antes del catálogo Metas.'
            );
        }
    }

    /**
     * Garantiza un año default (EnsureIndicatorYearIsSet / Year::getDefaultYear).
     */
    private function ensureDefaultYear(): void
    {
        if (! Schema::hasTable('years')) {
            return;
        }

        $now = now();
        $ano = (int) $now->year;

        DB::table('years')->where('default', true)->update(['default' => false]);

        $existingId = DB::table('years')->where('ano', $ano)->value('id');

        if ($existingId !== null) {
            DB::table('years')->where('id', $existingId)->update([
                'default' => true,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('years')->insert([
                'ano' => $ano,
                'default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('default_year');
    }

    private function fixtureDirectory(): string
    {
        if (app()->environment('testing')) {
            return database_path('seeders/fixtures/usable-catalog-testing');
        }

        return database_path('seeders/fixtures/usable-catalog');
    }

    private function seedTable(string $fixtureDir, string $table): void
    {
        if (! Schema::hasTable($table)) {
            $this->command?->warn("Omitida {$table}: tabla inexistente en este esquema.");

            return;
        }

        $path = $fixtureDir.DIRECTORY_SEPARATOR.$table.'.json';
        if (! is_file($path)) {
            throw new RuntimeException("Fixture faltante: {$path}");
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        DB::table($table)->delete();

        if ($rows === []) {
            return;
        }

        $columns = Schema::getColumnListing($table);
        $chunk = [];

        foreach ($rows as $row) {
            $filtered = [];
            foreach ($row as $key => $value) {
                if (in_array($key, $columns, true)) {
                    $filtered[$key] = $value;
                }
            }
            if ($filtered !== []) {
                $chunk[] = $filtered;
            }

            if (count($chunk) >= 200) {
                DB::table($table)->insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            DB::table($table)->insert($chunk);
        }
    }
}
