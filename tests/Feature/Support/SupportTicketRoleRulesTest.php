<?php

namespace Tests\Feature\Support;

use App\Enums\SupportTicketArea;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Year;
use App\Services\Support\SupportTicketAssignmentService;
use App\Support\Voyager\BrowseSoporteVoyagerMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class SupportTicketRoleRulesTest extends TestCase
{
    use RefreshDatabase;

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

        BrowseSoporteVoyagerMenu::syncRoleGrants();
    }

    private function attachBrowseAdmin(Role $role): void
    {
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    public function test_parametrizador_cannot_access_support_routes_even_with_browse_admin(): void
    {
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $this->attachBrowseAdmin($paramRole);

        $user = User::factory()->create(['role_id' => $paramRole->id, 'cen_hie_dependency_id' => 1]);

        $this->actingAs($user)
            ->get(route('centinela.support.index'))
            ->assertForbidden();
    }

    public function test_visualizador_sees_only_tickets_created_by_self(): void
    {
        $vizRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $this->attachBrowseAdmin($vizRole);

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->attachBrowseAdmin($cgRole);

        $viz = User::factory()->create(['role_id' => $vizRole->id, 'cen_hie_dependency_id' => 1]);
        $other = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);

        $mine = SupportTicket::factory()->create([
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => $viz->id,
            'subject' => 'Ticket propio visualizador',
        ]);
        $notMine = SupportTicket::factory()->create([
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => $other->id,
            'subject' => 'Ticket ajeno no debe listarse',
        ]);

        $response = $this->actingAs($viz)
            ->get(route('centinela.support.index'))
            ->assertOk();

        $response->assertSee('Ticket propio visualizador', false);
        $response->assertDontSee('Ticket ajeno no debe listarse', false);

        $this->actingAs($viz)
            ->get(route('centinela.support.show', $notMine))
            ->assertForbidden();
    }

    public function test_visualizador_cannot_be_assignment_target(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $vizRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);

        $admin = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $visualizador = User::factory()->create(['role_id' => $vizRole->id, 'cen_hie_dependency_id' => 1]);

        $ticket = SupportTicket::factory()->create([
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => $admin->id,
            'area' => SupportTicketArea::Gestion,
        ]);

        $service = app(SupportTicketAssignmentService::class);

        $this->assertFalse($service->targetIsAssignable($ticket, $admin, $visualizador));
    }

    public function test_browse_soporte_is_not_granted_to_parametrizador_after_sync(): void
    {
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $permId = Permission::query()->where('key', 'browse_soporte')->value('id');
        $this->assertNotNull($permId);

        $this->assertFalse(
            DB::table('permission_role')
                ->where('role_id', $paramRole->id)
                ->where('permission_id', (int) $permId)
                ->exists()
        );
    }
}
