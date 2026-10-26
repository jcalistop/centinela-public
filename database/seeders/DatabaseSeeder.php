<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Instalación limpia (SDD-240 / runbook) — BD del .env (DB_DATABASE):
     * 1) `CENTINELA_ALLOW_DESTRUCTIVE_DATABASE_COMMANDS=true`
     * 2) `composer run schema:fresh` (wipe + dump + migrate en conexión default)
     * 3) `php artisan db:seed` — este seeder (o `centinela:schema-fresh --seed`).
     * mysql_schema_parity NO es este camino (tool dump; ver guides/schema-dump-parity-tool.md).
     *
     * Orden: catálogos allow-list → bootstrap admin (parches menú/BREAD/año) → usuario ejemplo.
     * CentinelaSqlTableSeeder está retirado (stub); no forma parte del camino default.
     *
     * BD ya existente: `php artisan migrate` (no schema:fresh).
     */
    public function run(): void
    {
        $this->call([
            UsableCatalogSeeder::class,
            MinimalPlatformSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
