<?php

namespace App\Support\Console;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

final class DestructiveMigrationSafety
{
    /** @var list<string> */
    public const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'db:wipe',
    ];

    public static function isDestructiveCommandName(string $command): bool
    {
        return in_array($command, self::DESTRUCTIVE_COMMANDS, true);
    }

    /**
     * Resuelve el driver de base de datos de la opción `--database`, o por defecto conexión configurada.
     *
     * @param  mixed  $databaseOptionFromInput  Valor Symfony de la opción `database` (puede ser string vacío/null).
     */
    public static function resolveConnectionDriver(Application $application, mixed $databaseOptionFromInput): string
    {
        /** @var Repository $configuration */
        $configuration = $application->make('config');

        $name = null;
        if (is_string($databaseOptionFromInput) && trim($databaseOptionFromInput) !== '') {
            $name = trim($databaseOptionFromInput);
        }

        if ($name === null) {
            /** @var string $name */
            $name = (string) $configuration->get('database.default');
        }

        /** @var string */
        return (string) $configuration->get("database.connections.{$name}.driver", '');
    }

    /**
     * Cuando debe bloquearse el comando destructivo: devuelve mensaje para excepción; null si puede continuar.
     */
    public static function denyReason(Application $application, string $driver): ?string
    {
        if ($application->runningUnitTests()) {
            return null;
        }

        /** @var Repository $config */
        $config = $application->make('config');

        if (filter_var($config->get('centinela.allow_destructive_database_commands'), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if ($driver === 'sqlite') {
            return null;
        }

        return 'Comando bloqueado (SDD-053 / ADR-026): `migrate:fresh`, `migrate:refresh` y `db:wipe` están bloqueados '
            ."contra conexiones driver `{$driver}` para evitar pérdida de datos. "
            .'Opciones: ejecutar `composer run test:migrate:fresh`; `php artisan migrate:fresh --env=testing`; '
            .'usar opción Artisan `--database` apuntando a una conexión SQLite; o, solo con decisión humana explícita '
            .'y backup, establecer `CENTINELA_ALLOW_DESTRUCTIVE_DATABASE_COMMANDS=true` en `.env`.';
    }
}
