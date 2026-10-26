<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\PlatformAuthSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CloseExpiredPlatformAuthSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_closes_sessions_older_than_lifetime_even_when_usage_tracking_disabled(): void
    {
        config()->set('centinela.usage_tracking.enabled', false);
        config()->set('session.lifetime', 120);

        $user = User::factory()->create();

        $expired = PlatformAuthSession::query()->create([
            'user_id' => $user->id,
            'cen_hie_dependency_id' => null,
            'laravel_session_id' => 'old-session',
            'started_at' => now()->subHours(3),
        ]);

        $fresh = PlatformAuthSession::query()->create([
            'user_id' => $user->id,
            'cen_hie_dependency_id' => null,
            'laravel_session_id' => 'fresh-session',
            'started_at' => now()->subMinutes(10),
        ]);

        Artisan::call('platform:close-expired-auth-sessions');

        $this->assertNotNull($expired->fresh()->ended_at);
        $this->assertSame('timeout', $expired->fresh()->end_reason);
        $this->assertNull($fresh->fresh()->ended_at);
    }

    public function test_closes_orphan_open_sessions_keeping_most_recent(): void
    {
        config()->set('session.lifetime', 120);

        $user = User::factory()->create();

        $older = PlatformAuthSession::query()->create([
            'user_id' => $user->id,
            'cen_hie_dependency_id' => null,
            'laravel_session_id' => 's1',
            'started_at' => now()->subMinutes(30),
        ]);

        $newer = PlatformAuthSession::query()->create([
            'user_id' => $user->id,
            'cen_hie_dependency_id' => null,
            'laravel_session_id' => 's2',
            'started_at' => now()->subMinutes(5),
        ]);

        Artisan::call('platform:close-expired-auth-sessions');

        $this->assertNotNull($older->fresh()->ended_at);
        $this->assertSame('superseded', $older->fresh()->end_reason);
        $this->assertNull($newer->fresh()->ended_at);
    }
}
