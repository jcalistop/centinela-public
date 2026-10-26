<?php

namespace Tests\Feature\Support;

use App\Enums\SupportTicketStatus;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class SupportTicketEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $controlGestion;

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
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->controlGestion = User::factory()->create([
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

    public function test_update_status_creates_event_with_comment_and_internal_visibility(): void
    {
        $ticket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('centinela.support.status.update', $ticket), [
                'status' => SupportTicketStatus::InProgress->value,
                'comment' => 'Moviendo a trabajo en curso.',
                'is_internal' => '1',
            ])
            ->assertRedirect(route('centinela.support.show', $ticket));

        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $this->admin->id,
            'type' => SupportTicketEvent::TYPE_STATUS_CHANGED,
            'is_internal' => 1,
            'comment' => 'Moviendo a trabajo en curso.',
        ]);
    }

    public function test_assign_update_creates_event_and_non_admin_cannot_force_internal(): void
    {
        $ticket = SupportTicket::factory()->create([
            'assigned_to_user_id' => null,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->actingAs($this->controlGestion)
            ->patch(route('centinela.support.assign.update', $ticket), [
                'assigned_to_user_id' => $this->controlGestion->id,
                'comment' => 'Me asigno para revisar.',
                'is_internal' => '1',
            ])
            ->assertRedirect(route('centinela.support.show', $ticket));

        $row = SupportTicketEvent::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('type', SupportTicketEvent::TYPE_ASSIGNMENT_CHANGED)
            ->latest('id')
            ->firstOrFail();

        $this->assertFalse($row->is_internal);
        $this->assertSame('Me asigno para revisar.', $row->comment);
        $this->assertIsArray($row->meta);
        $this->assertSame($this->controlGestion->id, $row->meta['to_user_id']);
    }

    public function test_non_admin_does_not_see_internal_events_in_show_page(): void
    {
        $ticket = SupportTicket::factory()->create([
            'cen_hie_dependency_id' => 1,
        ]);

        SupportTicketEvent::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $this->admin->id,
            'type' => SupportTicketEvent::TYPE_STATUS_CHANGED,
            'is_internal' => true,
            'comment' => 'Nota interna',
            'meta' => ['from' => 'open', 'to' => 'in_progress'],
        ]);

        $this->actingAs($this->controlGestion)
            ->get(route('centinela.support.show', $ticket))
            ->assertOk()
            ->assertDontSee('Nota interna')
            ->assertDontSee('Interno');
    }
}
