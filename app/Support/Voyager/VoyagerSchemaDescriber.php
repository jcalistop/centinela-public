<?php

namespace App\Support\Voyager;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza `SchemaManager::describeTable()` de Voyager 1.8, que falla con
 * `Undefined array key 0` cuando el índice de una columna no ocupa las dos
 * primeras posiciones y que entrega `key`/`null` en un formato que las vistas
 * del constructor BREAD y de `/admin/database` no reconocen.
 *
 * La salida replica la de Voyager 1.7: `key` sale del índice de una sola
 * columna de mayor prioridad (primario > único > simple) y `null` es YES/NO.
 */
final class VoyagerSchemaDescriber
{
    /**
     * @return Collection<int, array{field: string, type: string, null: string, key: ?string, default: mixed, extra: string, indexes: list<array<string, mixed>>}>
     */
    public static function describeTable(string $table): Collection
    {
        $schema = Schema::getConnection()->getSchemaBuilder();
        $indexes = $schema->getIndexes($table);

        return collect($schema->getColumns($table))->map(function (array $column) use ($indexes): array {
            $columnIndexes = self::singleColumnIndexes($indexes, $column['name']);

            return [
                'field' => $column['name'],
                'type' => $column['type_name'],
                'null' => $column['nullable'] ? 'YES' : 'NO',
                'key' => self::keyFor($columnIndexes[0] ?? null),
                'default' => $column['default'],
                'extra' => $column['auto_increment'] ? 'auto_increment' : '',
                'indexes' => $columnIndexes,
            ];
        });
    }

    /**
     * @param  array<int, array{name: string, columns: list<string>, type: ?string, unique: bool, primary: bool}>  $indexes
     * @return list<array{name: string, columns: list<string>, type: ?string, unique: bool, primary: bool}>
     */
    private static function singleColumnIndexes(array $indexes, string $column): array
    {
        return collect($indexes)
            ->filter(fn (array $index): bool => count($index['columns']) === 1
                && strcasecmp($index['columns'][0], $column) === 0)
            ->sortBy(fn (array $index): int => match (true) {
                $index['primary'] => 0,
                $index['unique'] => 1,
                default => 2,
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{unique: bool, primary: bool}|null  $index
     */
    private static function keyFor(?array $index): ?string
    {
        return match (true) {
            $index === null => null,
            $index['primary'] => 'PRI',
            $index['unique'] => 'UNI',
            default => 'IND',
        };
    }
}
