<?php

namespace Tests\Unit\Support\Configuration;

use App\Support\Configuration\ConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationServiceBackupEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function backup_notification_email_prefers_centinela_config(): void
    {
        config([
            'services.backup.email_address' => 'from-services@example.com',
            'centinela.backup.email_address' => 'from-centinela@example.com',
        ]);

        $service = new ConfigurationService;

        $this->assertSame('from-centinela@example.com', $service->backupNotificationEmail());
    }

    /** @test */
    public function backup_notification_email_falls_back_to_services_config(): void
    {
        config([
            'centinela.backup.email_address' => null,
            'services.backup.email_address' => 'from-services@example.com',
        ]);

        $service = new ConfigurationService;

        $this->assertSame('from-services@example.com', $service->backupNotificationEmail());
    }
}
