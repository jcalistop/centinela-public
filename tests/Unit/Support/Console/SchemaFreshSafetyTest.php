<?php

namespace Tests\Unit\Support\Console;

use App\Support\Console\SchemaFreshSafety;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SchemaFreshSafetyTest extends TestCase
{
    private function dumpFile(): string
    {
        $dump = tempnam(sys_get_temp_dir(), 'schema');
        $this->assertIsString($dump);
        file_put_contents($dump, '-- dump');

        return $dump;
    }

    public function test_allows_local_mysql_with_destructive_flag(): void
    {
        $dump = $this->dumpFile();

        $config = new Repository([
            'centinela.allow_destructive_database_commands' => true,
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'centinela',
            'database.connections.mysql_schema_parity.database' => 'centinela_schema_parity',
        ]);

        try {
            SchemaFreshSafety::assertAllowed($config, $dump, 'local', 'mysql');
            $this->assertTrue(true);
        } finally {
            unlink($dump);
        }
    }

    public function test_allows_sqlite_without_destructive_flag(): void
    {
        $dump = $this->dumpFile();

        $config = new Repository([
            'centinela.allow_destructive_database_commands' => false,
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        try {
            SchemaFreshSafety::assertAllowed($config, $dump, 'local', 'sqlite');
            $this->assertTrue(true);
        } finally {
            unlink($dump);
        }
    }

    public function test_rejects_missing_dump(): void
    {
        $config = new Repository([
            'centinela.allow_destructive_database_commands' => true,
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'centinela',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dump de esquema no encontrado');

        SchemaFreshSafety::assertAllowed(
            $config,
            sys_get_temp_dir().'/no-existe-mysql-schema.sql',
            'local',
            'mysql',
        );
    }

    public function test_rejects_production(): void
    {
        $dump = $this->dumpFile();

        $config = new Repository([
            'centinela.allow_destructive_database_commands' => true,
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'centinela',
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('production');
            SchemaFreshSafety::assertAllowed($config, $dump, 'production', 'mysql');
        } finally {
            unlink($dump);
        }
    }

    public function test_rejects_mysql_without_destructive_flag(): void
    {
        $dump = $this->dumpFile();

        $config = new Repository([
            'centinela.allow_destructive_database_commands' => false,
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'centinela',
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('CENTINELA_ALLOW_DESTRUCTIVE_DATABASE_COMMANDS');
            SchemaFreshSafety::assertAllowed($config, $dump, 'local', 'mysql');
        } finally {
            unlink($dump);
        }
    }

    public function test_rejects_when_default_is_parity_database(): void
    {
        $dump = $this->dumpFile();

        $config = new Repository([
            'centinela.allow_destructive_database_commands' => true,
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'centinela_schema_parity',
            'database.connections.mysql_schema_parity.database' => 'centinela_schema_parity',
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('paridad');
            SchemaFreshSafety::assertAllowed($config, $dump, 'local', 'mysql');
        } finally {
            unlink($dump);
        }
    }
}
