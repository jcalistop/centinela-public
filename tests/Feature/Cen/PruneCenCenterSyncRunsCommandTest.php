<?php

namespace Tests\Feature\Cen;

use App\Models\CenCenterSyncRun;
use App\Models\CenCenterSyncRunIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PruneCenCenterSyncRunsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_deletes_old_runs_and_issues_but_keeps_recent(): void
    {
        Config::set('centinela.national_establishments.sync_runs_retention_months', 6);

        $oldRun = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '24',
            'status' => 'completed',
            'started_at' => now()->subMonths(7),
            'finished_at' => now()->subMonths(7),
        ]);

        CenCenterSyncRunIssue::query()->create([
            'cen_center_sync_run_id' => $oldRun->id,
            'severity' => 'warning',
            'code' => 'unmapped_enum',
            'message' => 'antigua',
        ]);

        $recentRun = CenCenterSyncRun::query()->create([
            'scope' => 'full',
            'status' => 'completed',
            'started_at' => now()->subMonth(),
            'finished_at' => now()->subMonth(),
        ]);

        CenCenterSyncRunIssue::query()->create([
            'cen_center_sync_run_id' => $recentRun->id,
            'severity' => 'info',
            'code' => 'glosa_equiv_applied',
            'message' => 'reciente',
        ]);

        $this->artisan('cen:sync-runs:prune', ['--no-interaction' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('cen_center_sync_runs', ['id' => $oldRun->id]);
        $this->assertDatabaseMissing('cen_center_sync_run_issues', [
            'cen_center_sync_run_id' => $oldRun->id,
        ]);
        $this->assertDatabaseHas('cen_center_sync_runs', ['id' => $recentRun->id]);
        $this->assertDatabaseHas('cen_center_sync_run_issues', [
            'cen_center_sync_run_id' => $recentRun->id,
        ]);
    }

    public function test_prune_dry_run_does_not_delete(): void
    {
        $oldRun = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '24',
            'status' => 'completed',
            'started_at' => now()->subYear(),
            'finished_at' => now()->subYear(),
        ]);

        $this->artisan('cen:sync-runs:prune', ['--dry-run' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cen_center_sync_runs', ['id' => $oldRun->id]);
    }

    public function test_prune_skips_running_runs(): void
    {
        $runningRun = CenCenterSyncRun::query()->create([
            'scope' => 'full',
            'status' => 'running',
            'started_at' => now()->subYear(),
        ]);

        $this->artisan('cen:sync-runs:prune', ['--no-interaction' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cen_center_sync_runs', ['id' => $runningRun->id]);
    }
}
