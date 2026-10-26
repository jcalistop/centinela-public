<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class SecurityAuditPruneTest extends TestCase
{
    /** @test */
    public function security_audit_check_prunes_old_failed_login_entries(): void
    {
        config([
            'centinela.security.failed_login_retention_days' => 7,
            'centinela.security.failed_login_lookback_hours' => 24,
            'centinela.security.failed_login_threshold' => 100,
            'centinela.security.alert_email_to' => '',
        ]);

        $logPath = storage_path('logs/failed-logins.log');
        if (! is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }

        $old = json_encode([
            'timestamp' => Carbon::now()->subDays(10)->toIso8601String(),
            'ip_address' => '1.1.1.1',
            'email' => 'old@test.local',
        ], JSON_THROW_ON_ERROR);
        $fresh = json_encode([
            'timestamp' => Carbon::now()->subHours(2)->toIso8601String(),
            'ip_address' => '2.2.2.2',
            'email' => 'fresh@test.local',
        ], JSON_THROW_ON_ERROR);

        file_put_contents($logPath, $old."\n".$fresh."\n");

        $this->artisan('security:audit-check')->assertSuccessful();

        $content = (string) file_get_contents($logPath);
        $this->assertStringNotContainsString('old@test.local', $content);
        $this->assertStringContainsString('fresh@test.local', $content);
    }
}
