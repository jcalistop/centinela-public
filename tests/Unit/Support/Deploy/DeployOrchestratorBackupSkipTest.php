<?php

namespace Tests\Unit\Support\Deploy;

use App\Support\Configuration\ConfigurationService;
use App\Support\Database\DatabaseBackupSkipGuard;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use Tests\TestCase;

class DeployOrchestratorBackupSkipTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @return array{ok: bool, status: string}
     */
    private function invokeRunBackup(): array
    {
        $method = new ReflectionMethod(DeployOrchestrator::class, 'runBackup');
        $method->setAccessible(true);

        /** @var array{ok: bool, status: string} $result */
        $result = $method->invoke(new DeployOrchestrator);

        return $result;
    }

    /** @test */
    public function run_backup_skips_mysqldump_when_recent_file_exists(): void
    {
        $this->mock(DatabaseBackupSkipGuard::class, function ($mock): void {
            $mock->shouldReceive('shouldSkipScheduledBackup')->once()->andReturn(true);
        });

        Artisan::shouldReceive('call')->never();

        $result = $this->invokeRunBackup();

        $this->assertTrue($result['ok']);
        $this->assertSame('skipped', $result['status']);
    }

    /** @test */
    public function run_backup_dumps_when_no_recent_file(): void
    {
        $this->mock(DatabaseBackupSkipGuard::class, function ($mock): void {
            $mock->shouldReceive('shouldSkipScheduledBackup')->once()->andReturn(false);
        });

        /** @var ConfigurationService&MockInterface $configuration */
        $configuration = Mockery::mock(ConfigurationService::class);
        $configuration->shouldReceive('backupNotificationEmail')->once()->andReturn(null);
        $this->app->instance(ConfigurationService::class, $configuration);

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:backup', ['--skip-if-recent' => true])
            ->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('db:backup OK');

        $result = $this->invokeRunBackup();

        $this->assertTrue($result['ok']);
        $this->assertSame('ok', $result['status']);
    }
}
