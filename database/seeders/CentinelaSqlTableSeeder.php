<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Retirado (SDD-240). El SQL operativo `2025-08-22_centinela_clean.sql` ya no se distribuye.
 * Usar `php artisan db:seed` (UsableCatalogSeeder + MinimalPlatformSeeder + AdminUserSeeder).
 */
class CentinelaSqlTableSeeder extends Seeder
{
    public function run(): void
    {
        $message = 'CentinelaSqlTableSeeder está retirado (SDD-240). '
            .'Use: composer run schema:fresh && php artisan db:seed '
            .'(UsableCatalogSeeder — catálogos COMGES/CEN sin evaluaciones).';

        if ($this->command !== null) {
            $this->command->error('⛔ '.$message);
        }

        throw new \RuntimeException($message);
    }
}
