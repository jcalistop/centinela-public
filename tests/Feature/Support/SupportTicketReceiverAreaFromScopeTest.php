<?php

namespace Tests\Feature\Support;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\SupportTicketReceiver;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class SupportTicketReceiverAreaFromScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $receiverUser;

    private User $controlGestionReceptorUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('centinela.support.enabled', true);

        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Uno',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->attachBrowseAdminRole($adminRole);
        $this->attachBrowseAdminRole($cgRole);

        $this->admin = User::factory()->create([
            'name' => 'Admin Receptores',
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->receiverUser = User::factory()->create([
            'name' => 'Usuario Receptor',
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->controlGestionReceptorUser = User::factory()->create([
            'name' => 'Cg Receptor',
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
    }

    private function attachBrowseAdminRole(Role $role): void
    {
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    public function test_scope_global_sets_area_to_tecnico_without_posting_area(): void
    {
        $this->actingAs($this->admin)
            ->post(route('centinela.support.receivers.store'), [
                'scope' => SupportTicketReceiver::SCOPE_GLOBAL,
                'user_id' => $this->receiverUser->id,
                'cen_hie_dependency_id' => null,
                'is_primary' => '0',
                'enabled' => '1',
            ])
            ->assertRedirect(route('centinela.support.receivers.index'));

        $this->assertDatabaseHas('support_ticket_receivers', [
            'user_id' => $this->receiverUser->id,
            'scope' => SupportTicketReceiver::SCOPE_GLOBAL,
            'area' => 'tecnico',
            'cen_hie_dependency_id' => null,
        ]);
    }

    public function test_scope_ss_sets_area_to_gestion(): void
    {
        $this->actingAs($this->admin)
            ->post(route('centinela.support.receivers.store'), [
                'scope' => SupportTicketReceiver::SCOPE_SS,
                'user_id' => $this->controlGestionReceptorUser->id,
                'cen_hie_dependency_id' => 1,
                'is_primary' => '1',
                'enabled' => '1',
            ])
            ->assertRedirect(route('centinela.support.receivers.index'));

        $this->assertDatabaseHas('support_ticket_receivers', [
            'user_id' => $this->controlGestionReceptorUser->id,
            'scope' => SupportTicketReceiver::SCOPE_SS,
            'area' => 'gestion',
            'cen_hie_dependency_id' => 1,
        ]);
    }

    public function test_update_scope_ss_sets_area_even_if_client_posts_wrong_payload(): void
    {
        $receiver = SupportTicketReceiver::query()->create([
            'scope' => SupportTicketReceiver::SCOPE_GLOBAL,
            'cen_hie_dependency_id' => null,
            'area' => 'tecnico',
            'user_id' => $this->receiverUser->id,
            'is_primary' => false,
            'enabled' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('centinela.support.receivers.update', $receiver), [
                'scope' => SupportTicketReceiver::SCOPE_SS,
                'user_id' => $this->controlGestionReceptorUser->id,
                'cen_hie_dependency_id' => 1,
                'is_primary' => '0',
                'enabled' => '1',
            ])
            ->assertRedirect(route('centinela.support.receivers.index'));

        $receiver->refresh();
        $this->assertSame(SupportTicketReceiver::SCOPE_SS, $receiver->scope);
        $this->assertSame('gestion', $receiver->area?->value);
    }
}
