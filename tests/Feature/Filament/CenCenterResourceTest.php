<?php

namespace Tests\Feature\Filament;

use App\Enums\Cen\CenCenterOrigen;
use App\Filament\Catalogo\Resources\CenCenters\CenCenterResource;
use App\Filament\Catalogo\Resources\CenCenters\Pages\EditCenCenter;
use App\Filament\Catalogo\Resources\CenCenters\Pages\ListCenCenters;
use App\Jobs\SyncNationalEstablishmentsJob;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Policies\CenCenterPolicy;
use App\Services\Cen\CenCenterBrowseService;
use App\Support\Cen\CenCenterNationalSyncDispatcher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class CenCenterResourceTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    private User $admin;

    private User $referente;

    private User $controlGestion;

    private CenHieDependency $ssA;

    private CenHieDependency $ssB;

    protected function setUp(): void
    {
        parent::setUp();

        Session::forget(CenCenterBrowseService::SESSION_KEY);

        config(['centinela.filament.enabled' => true]);
        Filament::setCurrentPanel('catalogo');

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);

        $this->ssA = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'SS A',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $this->ssB = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '25',
            'nombre' => 'SS B',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->ssA->id,
        ]);
        $this->referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ssA->id,
        ]);
        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ssA->id,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->grantCenCenterPermissions($cgRole, ['browse', 'read', 'edit', 'add']);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'module.filament_establecimientos_enabled'],
            [
                'display_name' => 'Filament establecimientos',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 42,
                'group' => 'Modulos',
            ]
        );
    }

    public function test_guest_is_redirected_from_establecimientos_resource(): void
    {
        $this->get('/catalogo/establecimientos')->assertRedirect();
    }

    public function test_referente_without_permissions_cannot_access_panel_when_flag_on(): void
    {
        config(['centinela.filament.establecimientos_enabled' => true]);

        $this->actingAs($this->referente)
            ->get('/configuracion')
            ->assertForbidden();
    }

    public function test_admin_gets_forbidden_on_resource_when_flag_is_off(): void
    {
        config(['centinela.filament.establecimientos_enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/catalogo/establecimientos')
            ->assertForbidden();
    }

    public function test_admin_can_list_when_flag_is_on(): void
    {
        config(['centinela.filament.establecimientos_enabled' => true]);

        $center = $this->createMinimalCenCenter([
            'codigo' => 'ADM-1',
            'cen_hie_dependency_id' => $this->ssA->id,
            'nombre_corto' => 'Centro Admin',
        ]);

        $this->actingAs($this->admin)
            ->get('/catalogo/establecimientos')
            ->assertOk();

        Livewire::actingAs($this->admin)
            ->test(ListCenCenters::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$center]);
    }

    public function test_admin_with_ss_can_explore_other_ss_without_create(): void
    {
        config(['centinela.filament.establecimientos_enabled' => true]);

        $this->admin->cen_hie_dependency_id = $this->ssA->id;
        $this->admin->save();

        $centerB = $this->createMinimalCenCenter([
            'codigo' => 'B-1',
            'cen_hie_dependency_id' => $this->ssB->id,
            'nombre_corto' => 'Centro B',
        ]);

        $this->actingAs($this->admin);
        $browse = app(CenCenterBrowseService::class);
        $browse->applyRequestedSs($this->admin, $this->ssB->id);

        $this->assertFalse(CenCenterResource::canCreate());

        Livewire::actingAs($this->admin)
            ->test(ListCenCenters::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$centerB]);
    }

    public function test_control_gestion_cannot_access_catalogo_panel(): void
    {
        config(['centinela.filament.establecimientos_enabled' => true]);

        $this->actingAs($this->controlGestion)
            ->get('/catalogo/establecimientos')
            ->assertForbidden();
    }

    public function test_api_origin_center_cannot_be_edited(): void
    {
        config(['centinela.filament.establecimientos_enabled' => true]);

        $center = $this->createMinimalCenCenter([
            'codigo' => 'API-1',
            'cen_hie_dependency_id' => $this->ssA->id,
            'origen' => CenCenterOrigen::Api->value,
        ]);

        $this->assertFalse($this->admin->can('update', $center));

        Livewire::actingAs($this->admin)
            ->test(EditCenCenter::class, ['record' => $center->getKey()])
            ->assertForbidden();
    }

    public function test_manual_center_can_be_edited_when_flag_on(): void
    {
        config(['centinela.filament.establecimientos_enabled' => true]);

        $center = $this->createMinimalCenCenter([
            'codigo' => 'MAN-1',
            'cen_hie_dependency_id' => $this->ssA->id,
            'origen' => CenCenterOrigen::Manual->value,
            'nombre_corto' => 'Manual',
        ]);

        $this->assertTrue($this->admin->can('update', $center));

        Livewire::actingAs($this->admin)
            ->test(EditCenCenter::class, ['record' => $center->getKey()])
            ->assertOk()
            ->fillForm([
                'nombre_corto' => 'Manual Editado',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Manual Editado', $center->fresh()->nombre_corto);
    }

    public function test_sync_ss_dispatches_job_when_enabled(): void
    {
        config([
            'centinela.filament.establecimientos_enabled' => true,
            'centinela.national_establishments.enabled' => true,
        ]);

        Queue::fake();

        $this->controlGestion->cen_hie_dependency_id = $this->ssA->id;
        $this->controlGestion->save();

        $message = app(CenCenterNationalSyncDispatcher::class)
            ->dispatchServicioSalud($this->controlGestion);

        $this->assertStringContainsString('24', $message);
        Queue::assertPushed(SyncNationalEstablishmentsJob::class);
    }

    public function test_sync_ss_denied_when_national_flag_off(): void
    {
        config(['centinela.national_establishments.enabled' => false]);

        $this->assertFalse(
            app(CenCenterNationalSyncDispatcher::class)->canSyncServicioSalud($this->controlGestion)
        );
    }

    /**
     * @param  list<string>  $actions
     */
    private function grantCenCenterPermissions(Role $role, array $actions): void
    {
        foreach ($actions as $action) {
            $permission = Permission::firstOrCreate([
                'key' => $action.'_'.CenCenterPolicy::PERMISSION_TABLE,
                'table_name' => CenCenterPolicy::PERMISSION_TABLE,
            ]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
