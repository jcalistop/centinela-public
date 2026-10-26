<?php

namespace Tests\Unit\Support\Dashboard;

use App\Models\Role;
use App\Models\User;
use App\Support\Dashboard\NonAdminDashboardPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NonAdminDashboardPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/admin/dashboard/{section}/{slug?}', fn () => 'ok')
            ->name('centinela.dashboard.detail');
    }

    public function test_ambito_detail_url_for_parametrizador_uses_slug_route(): void
    {
        $role = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $user = User::factory()->create(['role_id' => $role->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($role)->save();

        $url = NonAdminDashboardPreview::ambitoDetailUrl($user, 'ges');

        $this->assertStringContainsString('/admin/dashboard/ambitos/ges', $url);
        $this->assertStringNotContainsString('preview=1', $url);
    }

    public function test_ambito_detail_url_for_admin_includes_preview_context(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $role->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($role)->save();

        $url = NonAdminDashboardPreview::ambitoDetailUrl($user, 'urgencia');

        $this->assertStringContainsString('/admin/dashboard/ambitos/urgencia', $url);
        $this->assertStringContainsString('preview=1', $url);
        $this->assertStringContainsString('ss=1', $url);
    }

    public function test_ambito_detail_links_disabled_when_non_admin_dashboard_off(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', false);

        $this->assertFalse(NonAdminDashboardPreview::ambitoDetailLinksEnabled());
    }
}
