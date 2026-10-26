<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Models\CenHieDependency;
use App\Models\PlatformAreaUsageSample;
use App\Models\PlatformAuthSession;
use App\Models\Role;
use App\Models\User;
use App\Support\Admin\AdminUsageDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUsageDashboardExcludeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('centinela.usage_tracking.enabled', true);
    }

    public function test_tables_and_charts_exclude_admin_primary_and_pivot_roles(): void
    {
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 301,
            'codigo' => 'SS301',
            'nombre' => 'Servicio Delta',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $admin = User::factory()->create([
            'name' => 'Solo Admin',
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $admin->role()->associate($adminRole)->save();

        $compound = User::factory()->create([
            'name' => 'Admin Compuesto',
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $compound->role()->associate($cgRole)->save();
        DB::table('user_roles')->insertOrIgnore([
            'user_id' => $compound->id,
            'role_id' => $adminRole->id,
        ]);

        $operator = User::factory()->create([
            'name' => 'Operador CG',
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $operator->role()->associate($cgRole)->save();

        foreach ([$admin, $compound, $operator] as $user) {
            PlatformAreaUsageSample::query()->create([
                'user_id' => $user->id,
                'cen_hie_dependency_id' => $ss->id,
                'area' => 'metas',
                'laravel_session_id' => 'sess-'.$user->id,
                'created_at' => now()->subDay(),
            ]);
        }

        PlatformAuthSession::query()->create([
            'user_id' => $admin->id,
            'cen_hie_dependency_id' => $ss->id,
            'laravel_session_id' => 'auth-admin',
            'started_at' => now()->subMinutes(5),
        ]);
        PlatformAuthSession::query()->create([
            'user_id' => $operator->id,
            'cen_hie_dependency_id' => $ss->id,
            'laravel_session_id' => 'auth-op',
            'started_at' => now()->subMinutes(5),
        ]);

        $service = app(AdminUsageDashboardService::class);
        $tables = $service->tablesPayload();
        $charts = $service->chartsPayload();

        $this->assertIsArray($tables);
        $this->assertIsArray($charts);

        $metas = collect($tables['areaTotals7'])->firstWhere('area', 'metas');
        $this->assertNotNull($metas);
        $this->assertSame(1, (int) $metas['hits']);
        $this->assertSame(1, (int) $metas['users']);

        $top = collect($tables['topSs7'])->firstWhere('id', $ss->id);
        $this->assertNotNull($top);
        $this->assertSame(1, (int) $top['users']);

        $this->assertSame(1, (int) $tables['authSessionsOpen']);
        $this->assertSame(['Operador CG'], collect($tables['recentUsers7'])->pluck('name')->all());

        $hits30 = array_sum(array_column($charts['dailySummary30'], 'hits'));
        $this->assertSame(1, $hits30);
    }
}
