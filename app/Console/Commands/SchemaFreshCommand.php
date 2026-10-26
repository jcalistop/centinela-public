<?php

namespace App\Console\Commands;

use App\Support\Console\SchemaFreshSafety;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Instalación limpia sobre la BD del `.env` (conexión default): wipe + dump canónico + migraciones.
 *
 * No confundir con mysql_schema_parity (tool de mantenimiento del dump — ver guides/schema-dump-parity-tool.md).
 */
class SchemaFreshCommand extends Command
{
    protected $signature = 'centinela:schema-fresh
                            {--seed : Ejecutar db:seed en la misma conexión tras migrar}
                            {--schema-path=database/schema/mysql-schema.sql : Path al dump canónico}
                            {--database= : Conexión (default: database.default del .env)}';

    protected $description = 'Wipe + migrate con schema dump en la BD del .env (instalación limpia Centinela)';

    public function handle(): int
    {
        $connection = (string) ($this->option('database') ?: config('database.default'));
        $schemaPathOption = (string) $this->option('schema-path');
        $absoluteSchema = is_file($schemaPathOption)
            ? $schemaPathOption
            : base_path($schemaPathOption);

        try {
            /** @var Repository $config */
            $config = app('config');
            SchemaFreshSafety::assertAllowed(
                $config,
                $absoluteSchema,
                (string) app()->environment(),
                $connection,
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $database = (string) config("database.connections.{$connection}.database");

        $this->warn("DESTRUCTIVO: wipe de todas las tablas en [{$connection}] BD={$database}");
        $this->line("Dump: {$absoluteSchema}");

        try {
            Schema::connection($connection)->dropAllTables();
            $this->info('Tablas eliminadas.');

            $exitCode = Artisan::call('migrate', [
                '--database' => $connection,
                '--schema-path' => $absoluteSchema,
                '--force' => true,
            ], $this->output);

            if ($exitCode !== self::SUCCESS) {
                $this->error('migrate falló tras el wipe.');

                return $exitCode;
            }

            if ($this->option('seed')) {
                $seedExit = Artisan::call('db:seed', [
                    '--database' => $connection,
                    '--force' => true,
                ], $this->output);

                if ($seedExit !== self::SUCCESS) {
                    $this->error('db:seed falló.');

                    return $seedExit;
                }
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            DB::purge($connection);
        }

        $this->info("schema:fresh completado en {$database} (conexión {$connection}).");
        $this->comment('Siguiente (si no usaste --seed): php artisan db:seed');

        return self::SUCCESS;
    }
}
