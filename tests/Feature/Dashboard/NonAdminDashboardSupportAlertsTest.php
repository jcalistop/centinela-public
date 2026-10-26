<?php

namespace Tests\Feature\Dashboard;

use App\Enums\SupportTicketArea;
use App\Enums\SupportTicketStatus;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Support\Configuration\ConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Setting;
use Tests\TestCase;

class NonAdminDashboardSupportAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $controlGestion;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('centinela.support.enabled', true);

        $this->year = Year::unguarded(fn () => Year::create([
            'ano' => 2026,
            'default' => true,
        ]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $this->controlGestion = User::unguarded(fn () => User::create([
            'name' => 'CG Soporte',
            'email' => 'cg-soporte-dashboard@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $this->controlGestion->role()->associate($role)->save();
    }

    public function test_dashboard_includes_support_alerts_when_module_is_enabled(): void
    {
        $openTicket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'area' => SupportTicketArea::Gestion,
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => $this->controlGestion->id,
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $openTicket->id,
            'user_id' => $this->controlGestion->id,
            'body' => 'Mensaje inicial',
        ]);

        $inProgressTicket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::InProgress,
            'area' => SupportTicketArea::Tecnico,
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => $this->controlGestion->id,
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $inProgressTicket->id,
            'user_id' => $this->controlGestion->id,
            'body' => 'Seguimiento técnico',
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $inProgressTicket->id,
            'user_id' => User::factory()->create([
                'role_id' => $this->controlGestion->role_id,
                'cen_hie_dependency_id' => 1,
            ])->id,
            'body' => 'Respuesta del equipo',
        ]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $alertas = app(NonAdminDashboardService::class)->forUser($this->controlGestion)['alertas'] ?? [];

        $titles = collect($alertas)->pluck('title')->all();
        $this->assertContains('Soporte: tickets no leídos', $titles);
        $this->assertContains('Soporte: tickets abiertos', $titles);
        $this->assertContains('Soporte: tickets en curso', $titles);

        $unreadAlert = collect($alertas)->firstWhere('title', 'Soporte: tickets no leídos');
        $this->assertNotNull($unreadAlert);
        $this->assertStringContainsString('mensajes nuevos', $unreadAlert['body']);
        $this->assertSame(route('centinela.support.index'), $unreadAlert['url']);

        $openAlert = collect($alertas)->firstWhere('title', 'Soporte: tickets abiertos');
        $this->assertNotNull($openAlert);
        $this->assertSame(
            route('centinela.support.index', ['status' => SupportTicketStatus::Open->value]),
            $openAlert['url'],
        );
    }

    public function test_dashboard_omits_support_alerts_when_module_is_disabled_globally(): void
    {
        Setting::unguarded(fn () => Setting::updateOrCreate(
            ['key' => 'module.soporte_enabled'],
            [
                'display_name' => 'module.soporte_enabled',
                'value' => 'Off',
                'type' => 'radio_btn',
                'group' => 'module',
                'order' => 1,
            ]
        ));

        app(ConfigurationService::class)->invalidate();
        app(ConfigurationService::class)->overrideConfig();

        SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => $this->controlGestion->id,
        ]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $titles = collect(app(NonAdminDashboardService::class)->forUser($this->controlGestion)['alertas'] ?? [])
            ->pluck('title');

        $this->assertFalse($titles->contains(fn (string $title): bool => str_starts_with($title, 'Soporte:')));
    }

    public function test_dashboard_omits_support_alerts_when_module_is_disabled_for_ss(): void
    {
        ServiceModuleToggle::query()->create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'soporte',
            'enabled' => false,
        ]);

        SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => $this->controlGestion->id,
        ]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $titles = collect(app(NonAdminDashboardService::class)->forUser($this->controlGestion)['alertas'] ?? [])
            ->pluck('title');

        $this->assertFalse($titles->contains(fn (string $title): bool => str_starts_with($title, 'Soporte:')));
    }

    public function test_dashboard_omits_support_alerts_for_parametrizador(): void
    {
        $role = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $parametrizador = User::unguarded(fn () => User::create([
            'name' => 'Parametrizador',
            'email' => 'param-soporte-dashboard@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $parametrizador->role()->associate($role)->save();

        SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => $this->controlGestion->id,
        ]);

        $this->actingAs($parametrizador)->withSession(['indicator_year' => $this->year]);

        $titles = collect(app(NonAdminDashboardService::class)->forUser($parametrizador)['alertas'] ?? [])
            ->pluck('title');

        $this->assertFalse($titles->contains(fn (string $title): bool => str_starts_with($title, 'Soporte:')));
    }
}
