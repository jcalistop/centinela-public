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
use Tests\TestCase;

class AdminUsageDashboardRecentUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('centinela.usage_tracking.enabled', true);
    }

    public function test_tables_payload_lists_recent_non_admin_users_with_ss(): void
    {
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 201,
            'codigo' => 'SS201',
            'nombre' => 'Servicio Gamma',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $admin = User::factory()->create([
            'name' => 'Admin Invisible',
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $admin->role()->associate($adminRole)->save();

        $operator = User::factory()->create([
            'name' => 'Operador Visible',
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $operator->role()->associate($cgRole)->save();

        PlatformAreaUsageSample::query()->create([
            'user_id' => $admin->id,
            'cen_hie_dependency_id' => $ss->id,
            'area' => 'metas',
            'laravel_session_id' => 'sess-admin',
            'created_at' => now()->subHour(),
        ]);

        PlatformAreaUsageSample::query()->create([
            'user_id' => $operator->id,
            'cen_hie_dependency_id' => $ss->id,
            'area' => 'metas',
            'laravel_session_id' => 'sess-op',
            'created_at' => now()->subMinutes(30),
        ]);

        $payload = app(AdminUsageDashboardService::class)->tablesPayload();

        $this->assertIsArray($payload);
        $names = collect($payload['recentUsers7'])->pluck('name')->all();

        $this->assertContains('Operador Visible', $names);
        $this->assertNotContains('Admin Invisible', $names);
        $this->assertSame('Servicio Gamma', collect($payload['recentUsers7'])->firstWhere('name', 'Operador Visible')['ss_name'] ?? null);
    }
}
