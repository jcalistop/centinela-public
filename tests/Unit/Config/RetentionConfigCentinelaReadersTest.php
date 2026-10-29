<?php

namespace Tests\Unit\Config;

use App\Support\Health\HealthStatusService;
use ReflectionMethod;
use Tests\Support\IsolatesBackupStorage;
use Tests\TestCase;

class RetentionConfigCentinelaReadersTest extends TestCase
{
    use IsolatesBackupStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateBackupStorage();
    }

    protected function tearDown(): void
    {
        $this->cleanupIsolatedBackupStorage();

        parent::tearDown();
    }

    /** @test */
    public function retention_keys_resolve_from_centinela_overrides(): void
    {
        config([
            'centinela.audit.retention_months' => 9,
            'services.audit.retention_months' => 3,
            'centinela.backup.retention_days' => 11,
            'services.backup.retention_days' => 2,
            'centinela.security.failed_login_retention_days' => 21,
            'services.security.log_retention_days' => 5,
            'centinela.backup.max_age_hours' => 40,
            'services.backup.max_age_hours' => 30,
        ]);

        $this->assertSame(9, (int) config('centinela.audit.retention_months'));
        $this->assertSame(11, (int) config('centinela.backup.retention_days'));
        $this->assertSame(21, (int) config('centinela.security.failed_login_retention_days'));
        $this->assertSame(40, (int) config('centinela.backup.max_age_hours'));
    }

    /** @test */
    public function health_backup_status_uses_centinela_max_age_hours(): void
    {
        config(['centinela.backup.max_age_hours' => 40]);

        $service = new HealthStatusService;
        $method = new ReflectionMethod(HealthStatusService::class, 'backupStatus');
        $method->setAccessible(true);

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir.'/backup_retention_test_'.uniqid('', true).'.sql';
        file_put_contents($file, 'x');
        touch($file, time() - (3600 * 35));

        /** @var array<string, mixed> $status */
        $status = $method->invoke($service);

        $this->assertSame('ok', $status['status']);

        unlink($file);
    }

    /** @test */
    public function health_worker_status_uses_centinela_thresholds(): void
    {
        config([
            'centinela.worker.stale_minutes' => 42,
            'services.worker.stale_minutes' => 9,
            'centinela.worker.pending_warning_jobs' => 7,
            'services.worker.pending_warning_jobs' => 1,
            'centinela.worker.failed_jobs_warning_24h' => 11,
            'services.worker.failed_jobs_warning_24h' => 2,
        ]);

        $service = new HealthStatusService;
        $method = new ReflectionMethod(HealthStatusService::class, 'workerStatus');
        $method->setAccessible(true);

        /** @var array<string, mixed> $status */
        $status = $method->invoke($service);

        $this->assertSame(42, (int) ($status['stale_threshold_minutes'] ?? 0));
    }

    /** @test */
    public function services_backup_max_age_default_aligned_to_twenty_four(): void
    {
        $this->assertSame(24, (int) config('centinela.backup.max_age_hours'));
        $this->assertSame(24, (int) config('services.backup.max_age_hours'));
    }

    /** @test */
    public function health_backup_status_warns_when_older_than_twenty_four_hours(): void
    {
        config(['centinela.backup.max_age_hours' => 24]);

        $service = new HealthStatusService;
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir.'/backup_retention_warn_'.uniqid('', true).'.sql';
        file_put_contents($file, 'x');
        touch($file, time() - (3600 * 25));

        /** @var array<string, mixed> $status */
        $status = $service->backupStatus();

        $this->assertSame(basename($file), $status['last_file'] ?? null);
        $this->assertSame('warning', $status['status']);

        unlink($file);
    }
}
