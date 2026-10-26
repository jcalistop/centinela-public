<?php

namespace Tests\Unit\Enums;

use App\Enums\Cen\CenCenterSyncRunChangeAction;
use App\Models\CenCenterSyncRun;
use PHPUnit\Framework\TestCase;

class CenCenterSyncRunChangeActionTest extends TestCase
{
    public function test_default_for_run_prefers_updated_when_only_updates(): void
    {
        $run = new CenCenterSyncRun([
            'created_count' => 0,
            'updated_count' => 5,
            'soft_deleted_count' => 0,
            'skipped_count' => 0,
            'restored_count' => 0,
        ]);

        $this->assertSame(
            CenCenterSyncRunChangeAction::Updated,
            CenCenterSyncRunChangeAction::defaultForRun($run),
        );
    }

    public function test_count_for_run_reads_aggregate_column(): void
    {
        $run = new CenCenterSyncRun(['skipped_count' => 7]);

        $this->assertSame(7, CenCenterSyncRunChangeAction::Skipped->countForRun($run));
    }
}
