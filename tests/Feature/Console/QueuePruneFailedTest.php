<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueuePruneFailedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function queue_prune_failed_keeps_recent_and_removes_old(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->markTestSkipped('Tabla failed_jobs no disponible');
        }

        DB::table('failed_jobs')->delete();

        $oldId = DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'OldJob'], JSON_THROW_ON_ERROR),
            'exception' => 'old',
            'failed_at' => now()->subHours(200)->toDateTimeString(),
        ]);

        $freshId = DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'FreshJob'], JSON_THROW_ON_ERROR),
            'exception' => 'fresh',
            'failed_at' => now()->subHours(2)->toDateTimeString(),
        ]);

        $this->artisan('queue:prune-failed', ['--hours' => 168])->assertSuccessful();

        $this->assertNull(DB::table('failed_jobs')->find($oldId));
        $this->assertNotNull(DB::table('failed_jobs')->find($freshId));
    }
}
