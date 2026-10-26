<?php

declare(strict_types=1);

namespace Tests\Feature\Configuracion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeploySettingsTabMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_settings_are_grouped_under_deploy_tab(): void
    {
        $keys = [
            'module.deploy_automatic_enabled',
            'services.deploy_branch',
            'services.deploy_remote',
            'services.git_binary_path',
            'services.deploy_run_composer',
            'services.deploy_run_npm_build',
            'system.deploy_executions_retention_days',
            'services.github_releases_repo',
        ];

        foreach ($keys as $key) {
            $this->assertSame(
                'Deploy',
                DB::table('settings')->where('key', $key)->value('group'),
                "Setting {$key} should be in Deploy group"
            );
        }
    }

    public function test_db_dump_path_is_grouped_under_sistema_tab(): void
    {
        $this->assertSame(
            'Sistema',
            DB::table('settings')->where('key', 'services.db_dump_path')->value('group')
        );
    }
}
