<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Models\CenHieDependency;
use App\Models\PlatformAreaUsageSample;
use App\Models\Role;
use App\Models\User;
use App\Support\Admin\AdminUsageDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUsageDashboardTopSsBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('centinela.usage_tracking.enabled', true);
    }

    public function test_tables_payload_batches_top_ss_dependency_lookup(): void
    {
        $ssA = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 101,
            'codigo' => 'SSA',
            'nombre' => 'Servicio Alpha',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $ssB = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 102,
            'codigo' => 'SSB',
            'nombre' => 'Servicio Beta',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $userA = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $ssA->id,
        ]);
        $userA->role()->associate($cgRole)->save();

        $userB = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $ssB->id,
        ]);
        $userB->role()->associate($cgRole)->save();

        foreach ([$userA, $userB] as $user) {
            PlatformAreaUsageSample::query()->create([
                'user_id' => $user->id,
                'cen_hie_dependency_id' => $user->cen_hie_dependency_id,
                'area' => 'metas',
                'laravel_session_id' => 'sess-'.$user->id,
                'created_at' => now()->subDay(),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $payload = app(AdminUsageDashboardService::class)->tablesPayload();

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(static fn (string $sql): bool => str_contains(strtolower($sql), 'cen_hie_dependencies'));

        DB::disableQueryLog();

        $this->assertIsArray($payload);
        $this->assertGreaterThanOrEqual(1, $queries->count(), 'Expected batched lookup on cen_hie_dependencies');
        $this->assertSame('Servicio Alpha', collect($payload['topSs7'])->firstWhere('id', 101)['name'] ?? null);
        $this->assertSame('Servicio Beta', collect($payload['topSs7'])->firstWhere('id', 102)['name'] ?? null);
    }
}
