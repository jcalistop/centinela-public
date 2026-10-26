<?php

namespace Tests\Feature\Deploy;

use App\Models\DeployExecution;
use App\Support\Deploy\DeployStepRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CentinelaDeployPruneExecutionsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function prune_deletes_finished_executions_older_than_retention(): void
    {
        config([
            'centinela.deploy.executions_retention_days' => 30,
            'centinela.deploy.artifacts_path' => 'logs/deploy-prune-test',
        ]);

        $old = DeployExecution::query()->create([
            'mode' => 'auto',
            'status' => 'succeeded',
            'branch' => 'main',
            'started_at' => now()->subDays(60),
            'finished_at' => now()->subDays(60),
        ]);

        $artifactDir = DeployStepRecorder::artifactsBasePath().DIRECTORY_SEPARATOR.(string) $old->id;
        File::makeDirectory($artifactDir, 0755, true);
        File::put($artifactDir.DIRECTORY_SEPARATOR.'migrate.txt', 'old transcript');

        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'branch' => 'main',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);

        $this->artisan('centinela:deploy:prune-executions')
            ->assertSuccessful();

        $this->assertSame(1, DeployExecution::query()->count());
        $this->assertDirectoryDoesNotExist($artifactDir);

        $base = DeployStepRecorder::artifactsBasePath();
        if (is_dir($base)) {
            File::deleteDirectory($base);
        }
    }
}
