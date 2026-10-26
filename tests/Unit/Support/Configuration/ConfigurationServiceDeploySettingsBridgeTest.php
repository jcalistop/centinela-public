<?php

namespace Tests\Unit\Support\Configuration;

use App\Support\Configuration\ConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfigurationServiceDeploySettingsBridgeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function override_config_applies_deploy_runtime_settings_from_database(): void
    {
        DB::table('settings')
            ->where('key', 'services.deploy_run_composer')
            ->update(['value' => 'On']);
        DB::table('settings')
            ->where('key', 'services.deploy_run_npm_build')
            ->update(['value' => 'Off']);
        DB::table('settings')
            ->where('key', 'system.deploy_executions_retention_days')
            ->update(['value' => '120']);

        $service = new ConfigurationService;
        $service->overrideConfig();

        $this->assertTrue((bool) config('centinela.deploy.run_composer_after_pull'));
        $this->assertFalse((bool) config('centinela.deploy.run_npm_build_after_pull'));
        $this->assertSame('120', config('centinela.deploy.executions_retention_days'));
    }
}
