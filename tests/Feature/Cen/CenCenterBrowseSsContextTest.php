<?php

namespace Tests\Feature\Cen;

use App\Enums\Cen\CenCenterOrigen;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Policies\CenCenterPolicy;
use App\Services\Cen\CenCenterBrowseService;
use App\Services\Cen\CenCenterExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class CenCenterBrowseSsContextTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Session::forget(CenCenterBrowseService::SESSION_KEY);
    }

    public function test_view_allows_explored_ss_and_update_stays_on_own_ss(): void
    {
        [$user, $centerA, $centerB] = $this->makeCgWithTwoCenters();

        $this->actingAs($user);
        app(CenCenterBrowseService::class)->applyRequestedSs($user, $centerB->cen_hie_dependency_id);

        $this->assertTrue($user->can('view', $centerA));
        $this->assertTrue($user->can('view', $centerB));
        $this->assertTrue($user->can('update', $centerA));
        $this->assertFalse($user->can('update', $centerB));
        $this->assertFalse($user->can('delete', $centerB));
        $this->assertSame($centerA->cen_hie_dependency_id, $user->fresh()->cen_hie_dependency_id);
    }

    public function test_view_of_unselected_third_ss_is_denied(): void
    {
        [$user, $centerA, $centerB] = $this->makeCgWithTwoCenters();

        $ssC = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '26',
            'nombre' => 'SS C',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $centerC = $this->createMinimalCenCenter([
            'codigo' => 'C-1',
            'cen_hie_dependency_id' => $ssC->id,
            'origen' => CenCenterOrigen::Manual->value,
        ]);

        $this->actingAs($user);
        app(CenCenterBrowseService::class)->applyRequestedSs($user, $centerB->cen_hie_dependency_id);

        $this->assertFalse($user->can('view', $centerC));
        $this->assertTrue($user->can('view', $centerA));
    }

    public function test_export_uses_explored_ss(): void
    {
        [$user, $centerA, $centerB] = $this->makeCgWithTwoCenters();

        $this->actingAs($user);
        $browse = app(CenCenterBrowseService::class);
        $browse->applyRequestedSs($user, $centerB->cen_hie_dependency_id);

        $ids = $browse->scopedCentersQueryForBrowse($user)->pluck('id')->all();

        $this->assertContains($centerB->id, $ids);
        $this->assertNotContains($centerA->id, $ids);

        $response = app(CenCenterExcelExportService::class)->downloadForUser($user, 'hieFilter');
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @return array{0: User, 1: CenCenter, 2: CenCenter}
     */
    private function makeCgWithTwoCenters(): array
    {
        $ssA = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'SS A',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $ssB = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '25',
            'nombre' => 'SS B',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ssA->id,
        ]);
        $user->role()->associate($role)->save();

        foreach (['browse', 'read', 'edit', 'add', 'delete'] as $action) {
            $permission = Permission::firstOrCreate([
                'key' => $action.'_'.CenCenterPolicy::PERMISSION_TABLE,
                'table_name' => CenCenterPolicy::PERMISSION_TABLE,
            ]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $centerA = $this->createMinimalCenCenter([
            'codigo' => 'A-1',
            'cen_hie_dependency_id' => $ssA->id,
            'origen' => CenCenterOrigen::Manual->value,
        ]);
        $centerB = $this->createMinimalCenCenter([
            'codigo' => 'B-1',
            'cen_hie_dependency_id' => $ssB->id,
            'origen' => CenCenterOrigen::Manual->value,
        ]);

        return [$user, $centerA, $centerB];
    }
}
