<?php

namespace Tests\Unit\Support\Console;

use App\Support\Console\DestructiveMigrationSafety;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DestructiveMigrationSafetyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_recognizes_destructive_command_names(): void
    {
        $this->assertTrue(DestructiveMigrationSafety::isDestructiveCommandName('migrate:fresh'));
        $this->assertTrue(DestructiveMigrationSafety::isDestructiveCommandName('migrate:refresh'));
        $this->assertTrue(DestructiveMigrationSafety::isDestructiveCommandName('db:wipe'));
        $this->assertFalse(DestructiveMigrationSafety::isDestructiveCommandName('migrate'));
    }

    public function test_deny_reason_allows_testing_runner(): void
    {
        $application = $this->mockApplicationForDenyReason(
            runningUnitTests: true,
            allowDestructive: false
        );

        $this->assertNull(DestructiveMigrationSafety::denyReason($application, 'mysql'));
    }

    public function test_deny_reason_allows_sqlite_driver_even_when_not_testing(): void
    {
        $application = $this->mockApplicationForDenyReason(
            runningUnitTests: false,
            allowDestructive: false
        );

        $this->assertNull(DestructiveMigrationSafety::denyReason($application, 'sqlite'));
    }

    public function test_deny_reason_allows_mysql_when_centinela_flag_true(): void
    {
        $application = $this->mockApplicationForDenyReason(
            runningUnitTests: false,
            allowDestructive: true
        );

        $this->assertNull(DestructiveMigrationSafety::denyReason($application, 'mysql'));
    }

    public function test_deny_reason_blocks_mysql_without_bypass(): void
    {
        $application = $this->mockApplicationForDenyReason(
            runningUnitTests: false,
            allowDestructive: false
        );

        $reason = DestructiveMigrationSafety::denyReason($application, 'mysql');

        $this->assertIsString($reason);
        $this->assertStringContainsString('migrate:fresh', $reason);
        $this->assertStringContainsString('SDD-053', $reason);
    }

    public function test_resolves_mysql_driver_via_default_connection(): void
    {
        $config = new Repository([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
        ]);

        $application = $this->mockApplicationForResolve($config);

        $driver = DestructiveMigrationSafety::resolveConnectionDriver($application, null);

        $this->assertSame('mysql', $driver);
    }

    public function test_resolves_driver_from_explicit_database_option(): void
    {
        $config = new Repository([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.alt.driver' => 'sqlite',
            'database.connections.alt.database' => ':memory:',
        ]);

        $application = $this->mockApplicationForResolve($config);

        $driver = DestructiveMigrationSafety::resolveConnectionDriver($application, 'alt');

        $this->assertSame('sqlite', $driver);
    }

    private function mockApplicationForDenyReason(bool $runningUnitTests, bool $allowDestructive): Application|MockInterface
    {
        $config = new Repository([
            'centinela.allow_destructive_database_commands' => $allowDestructive,
        ]);

        /** @var Application|MockInterface $application */
        $application = Mockery::mock(Application::class);
        $application->shouldReceive('runningUnitTests')->once()->andReturn($runningUnitTests);

        $timesConfig = ($runningUnitTests === true) ? 0 : 1;
        $application->shouldReceive('make')
            ->with('config')
            ->times($timesConfig)
            ->andReturn($config);

        return $application;
    }

    private function mockApplicationForResolve(Repository $repository): Application|MockInterface
    {
        /** @var Application|MockInterface $application */
        $application = Mockery::mock(Application::class);
        $application->shouldReceive('make')->with('config')->once()->andReturn($repository);

        return $application;
    }
}
