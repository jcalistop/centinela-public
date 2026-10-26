<?php

namespace Tests\Unit\Support\Schema;

use App\Support\Schema\SchemaDiffComparer;
use Tests\TestCase;

class SchemaDiffComparerTest extends TestCase
{
    public function test_identical_schemas_yield_no_diffs(): void
    {
        $schema = $this->sampleSchema();

        $diffs = (new SchemaDiffComparer)->diff($schema, $schema);

        $this->assertSame([], $diffs);
    }

    public function test_detects_missing_and_extra_table(): void
    {
        $reference = $this->sampleSchema();
        $target = $this->sampleSchema();
        unset($target['tables']['roles']);
        $target['tables']['extra'] = ['engine' => 'InnoDB'];

        $diffs = (new SchemaDiffComparer)->diff($reference, $target);

        $this->assertContains('Falta tabla en target: roles', $diffs);
        $this->assertContains('Sobran tabla en target: extra', $diffs);
    }

    public function test_detects_column_and_index_and_fk_divergence(): void
    {
        $reference = $this->sampleSchema();
        $target = $this->sampleSchema();
        $target['columns']['users.email']['is_nullable'] = 'YES';
        $target['indexes']['users.users_email_unique']['columns'] = ['email', 'id'];
        $target['foreign_keys']['users.users_role_id_foreign']['delete_rule'] = 'CASCADE';

        $diffs = (new SchemaDiffComparer)->diff($reference, $target);

        $this->assertNotEmpty($diffs);
        $this->assertTrue(
            collect($diffs)->contains(fn (string $line): bool => str_contains($line, 'columna users.email'))
        );
        $this->assertTrue(
            collect($diffs)->contains(fn (string $line): bool => str_contains($line, 'índice users.users_email_unique'))
        );
        $this->assertTrue(
            collect($diffs)->contains(fn (string $line): bool => str_contains($line, 'FK users.users_role_id_foreign'))
        );
    }

    /**
     * @return array{
     *     tables: array<string, array{engine: string|null}>,
     *     columns: array<string, array{data_type: string, column_type: string, is_nullable: string, column_default: string|null}>,
     *     indexes: array<string, array{unique: bool, columns: list<string>}>,
     *     foreign_keys: array<string, array{column: string, ref_table: string, ref_column: string, update_rule: string, delete_rule: string}>
     * }
     */
    private function sampleSchema(): array
    {
        return [
            'tables' => [
                'roles' => ['engine' => 'InnoDB'],
                'users' => ['engine' => 'InnoDB'],
            ],
            'columns' => [
                'roles.id' => [
                    'data_type' => 'bigint',
                    'column_type' => 'bigint unsigned',
                    'is_nullable' => 'NO',
                    'column_default' => null,
                ],
                'users.email' => [
                    'data_type' => 'varchar',
                    'column_type' => 'varchar(255)',
                    'is_nullable' => 'NO',
                    'column_default' => null,
                ],
                'users.role_id' => [
                    'data_type' => 'bigint',
                    'column_type' => 'bigint unsigned',
                    'is_nullable' => 'YES',
                    'column_default' => null,
                ],
            ],
            'indexes' => [
                'users.users_email_unique' => [
                    'unique' => true,
                    'columns' => ['email'],
                ],
            ],
            'foreign_keys' => [
                'users.users_role_id_foreign' => [
                    'column' => 'role_id',
                    'ref_table' => 'roles',
                    'ref_column' => 'id',
                    'update_rule' => 'RESTRICT',
                    'delete_rule' => 'RESTRICT',
                ],
            ],
        ];
    }
}
