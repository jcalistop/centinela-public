<?php

namespace App\Console\Commands;

use App\Support\Schema\MysqlInformationSchemaReader;
use App\Support\Schema\SchemaDiffComparer;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SchemaDiffCommand extends Command
{
    protected $signature = 'centinela:schema-diff
                            {--reference= : Conexión de referencia (copia fiel / dump cargado). Default: database.default}
                            {--target= : Conexión a comparar (requerida; p. ej. mysql_schema_parity tras cargar el dump)}
                            {--require-voyager : Falla si faltan tablas Voyager listadas en SDD-219 en la referencia}';

    protected $description = 'TOOL dump: compara esquemas MySQL (paridad SDD-219). No instalar la app aquí.';

    public function handle(MysqlInformationSchemaReader $reader, SchemaDiffComparer $comparer): int
    {
        $this->warn('TOOL de mantenimiento del dump — no es instalación de Centinela.');
        $this->warn('Tras terminar: DROP DATABASE de la BD parity y quitar DB_SCHEMA_PARITY_* del .env.');
        $this->line('Guía: .github/docs/sdd/guides/schema-dump-parity-tool.md');
        $this->newLine();

        $reference = (string) ($this->option('reference') ?: config('database.default'));
        $target = $this->option('target');

        if (! is_string($target) || trim($target) === '') {
            $this->error('La opción --target= es requerida (conexión MySQL del esquema a comparar).');

            return self::FAILURE;
        }

        $target = trim($target);

        $this->warn('Ignorados justificados (ADR-040): orden físico de índices entre sí; comentarios de columna.');

        try {
            $referenceSchema = $reader->read($reference);
            $targetSchema = $reader->read($target);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('No se pudo leer information_schema: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Referencia [%s] BD=%s (%d tablas) vs target [%s] BD=%s (%d tablas)',
            $reference,
            $referenceSchema['database'],
            count($referenceSchema['tables']),
            $target,
            $targetSchema['database'],
            count($targetSchema['tables'])
        ));

        if ($this->option('require-voyager')) {
            $missingVoyager = [];
            foreach (MysqlInformationSchemaReader::VOYAGER_TABLES as $table) {
                if (! isset($referenceSchema['tables'][$table])) {
                    $missingVoyager[] = $table;
                }
            }

            if ($missingVoyager !== []) {
                $this->error('Faltan tablas Voyager en la referencia: '.implode(', ', $missingVoyager));

                return self::FAILURE;
            }

            $this->info('Tablas Voyager (SDD-219) presentes en referencia.');
        }

        $diffs = $comparer->diff($referenceSchema, $targetSchema);

        if ($diffs === []) {
            $this->info('Paridad OK: sin divergencias en tablas/columnas/índices/FKs.');
            $this->warn('Recuerda eliminar la BD de paridad (DROP DATABASE) para no confundirla con DB_DATABASE.');

            return self::SUCCESS;
        }

        $this->error('Paridad FALLÓ ('.count($diffs).' divergencias):');
        foreach ($diffs as $line) {
            $this->line('  - '.$line);
        }
        $this->warn('Aunque falle, elimina la BD parity al cerrar la sesión de auditoría del dump.');

        return self::FAILURE;
    }
}
