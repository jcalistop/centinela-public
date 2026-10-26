<?php

namespace App\Support\Schema;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lee el esquema MySQL vía information_schema (SDD-219 / ADR-040).
 */
final class MysqlInformationSchemaReader
{
    /** @var list<string> */
    public const VOYAGER_TABLES = [
        'data_types',
        'data_rows',
        'menus',
        'menu_items',
        'settings',
        'permissions',
        'permission_role',
        'roles',
        'translations',
    ];

    public function assertMysqlConnection(string $connection): void
    {
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($driver !== 'mysql') {
            throw new InvalidArgumentException(
                "La conexión [{$connection}] debe ser MySQL (driver actual: {$driver})."
            );
        }
    }

    /**
     * @return array{
     *     database: string,
     *     tables: array<string, array{engine: string|null}>,
     *     columns: array<string, array{data_type: string, column_type: string, is_nullable: string, column_default: string|null}>,
     *     indexes: array<string, array{unique: bool, columns: list<string>}>,
     *     foreign_keys: array<string, array{column: string, ref_table: string, ref_column: string, update_rule: string, delete_rule: string}>
     * }
     */
    public function read(string $connection): array
    {
        $this->assertMysqlConnection($connection);

        $database = (string) config("database.connections.{$connection}.database");
        if ($database === '') {
            throw new RuntimeException("La conexión [{$connection}] no tiene database configurada.");
        }

        $db = DB::connection($connection);

        /** @var list<object> $tableRows */
        $tableRows = $db->select(
            'SELECT TABLE_NAME AS table_name, ENGINE AS engine
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
            [$database, 'BASE TABLE']
        );

        $tables = [];
        foreach ($tableRows as $row) {
            $tables[(string) $row->table_name] = [
                'engine' => $row->engine !== null ? (string) $row->engine : null,
            ];
        }
        ksort($tables);

        /** @var list<object> $columnRows */
        $columnRows = $db->select(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name,
                    DATA_TYPE AS data_type, COLUMN_TYPE AS column_type,
                    IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$database]
        );

        $columns = [];
        foreach ($columnRows as $row) {
            $tableName = (string) $row->table_name;
            if (! isset($tables[$tableName])) {
                continue;
            }

            $key = $tableName.'.'.(string) $row->column_name;
            $columns[$key] = [
                'data_type' => strtolower((string) $row->data_type),
                'column_type' => strtolower((string) $row->column_type),
                'is_nullable' => strtoupper((string) $row->is_nullable),
                'column_default' => $this->normalizeDefault($row->column_default),
            ];
        }
        ksort($columns);

        /** @var list<object> $indexRows */
        $indexRows = $db->select(
            'SELECT TABLE_NAME AS table_name, INDEX_NAME AS index_name,
                    NON_UNIQUE AS non_unique, SEQ_IN_INDEX AS seq_in_index,
                    COLUMN_NAME AS column_name
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            [$database]
        );

        $indexes = [];
        foreach ($indexRows as $row) {
            $tableName = (string) $row->table_name;
            if (! isset($tables[$tableName])) {
                continue;
            }

            $key = $tableName.'.'.(string) $row->index_name;
            if (! isset($indexes[$key])) {
                $indexes[$key] = [
                    'unique' => (int) $row->non_unique === 0,
                    'columns' => [],
                ];
            }
            $indexes[$key]['columns'][] = (string) $row->column_name;
        }
        ksort($indexes);

        /** @var list<object> $fkColumnRows */
        $fkColumnRows = $db->select(
            'SELECT CONSTRAINT_NAME AS constraint_name, TABLE_NAME AS table_name,
                    COLUMN_NAME AS column_name,
                    REFERENCED_TABLE_NAME AS referenced_table_name,
                    REFERENCED_COLUMN_NAME AS referenced_column_name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION',
            [$database]
        );

        /** @var list<object> $fkRuleRows */
        $fkRuleRows = $db->select(
            'SELECT CONSTRAINT_NAME AS constraint_name, TABLE_NAME AS table_name,
                    UPDATE_RULE AS update_rule, DELETE_RULE AS delete_rule
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?',
            [$database]
        );

        $fkRules = [];
        foreach ($fkRuleRows as $row) {
            $fkRules[(string) $row->table_name.'.'.(string) $row->constraint_name] = [
                'update_rule' => strtoupper((string) $row->update_rule),
                'delete_rule' => strtoupper((string) $row->delete_rule),
            ];
        }

        $foreignKeys = [];
        foreach ($fkColumnRows as $row) {
            $tableName = (string) $row->table_name;
            if (! isset($tables[$tableName])) {
                continue;
            }

            $key = $tableName.'.'.(string) $row->constraint_name;
            $rules = $fkRules[$key] ?? ['update_rule' => '', 'delete_rule' => ''];
            $foreignKeys[$key] = [
                'column' => (string) $row->column_name,
                'ref_table' => (string) $row->referenced_table_name,
                'ref_column' => (string) $row->referenced_column_name,
                'update_rule' => $rules['update_rule'],
                'delete_rule' => $rules['delete_rule'],
            ];
        }
        ksort($foreignKeys);

        return [
            'database' => $database,
            'tables' => $tables,
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ];
    }

    private function normalizeDefault(mixed $default): ?string
    {
        if ($default === null) {
            return null;
        }

        $value = (string) $default;

        return $value === '' ? null : $value;
    }
}
