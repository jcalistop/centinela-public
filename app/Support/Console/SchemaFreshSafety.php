<?php

namespace App\Support\Console;

use Illuminate\Config\Repository;
use RuntimeException;

/**
 * Guardas para `centinela:schema-fresh` — instalación limpia sobre la conexión default del `.env` (ADR-026).
 */
final class SchemaFreshSafety
{
    /**
     * @throws RuntimeException
     */
    public static function assertAllowed(
        Repository $config,
        string $schemaPath,
        string $environment,
        string $connectionName,
    ): void {
        if (! is_file($schemaPath)) {
            throw new RuntimeException("Dump de esquema no encontrado: {$schemaPath}");
        }

        if ($environment === 'production') {
            throw new RuntimeException(
                'Abortado: centinela:schema-fresh no puede ejecutarse en APP_ENV=production (ADR-026).'
            );
        }

        $driver = (string) $config->get("database.connections.{$connectionName}.driver", '');
        $database = (string) $config->get("database.connections.{$connectionName}.database", '');

        if ($database === '') {
            throw new RuntimeException(
                "Conexión [{$connectionName}] sin nombre de base de datos. Revisar DB_DATABASE / .env."
            );
        }

        if ($driver === 'sqlite') {
            return;
        }

        if (! filter_var($config->get('centinela.allow_destructive_database_commands'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'Comando bloqueado (ADR-026 / SDD-053): wipe de esquema MySQL requiere '
                    .'CENTINELA_ALLOW_DESTRUCTIVE_DATABASE_COMMANDS=true en .env (solo local, consciente del riesgo). '
                    .'Alternativa segura de migraciones PHP: composer run test:migrate:fresh (SQLite).'
            );
        }

        $parityDb = (string) $config->get('database.connections.mysql_schema_parity.database', '');
        if ($parityDb !== '' && strcasecmp($database, $parityDb) === 0) {
            throw new RuntimeException(
                "Abortado: la conexión default apunta a la BD de paridad ({$parityDb}). "
                    .'Usa DB_DATABASE para la app; mysql_schema_parity es solo tool de dump (ver guides/schema-dump-parity-tool.md).'
            );
        }
    }
}
