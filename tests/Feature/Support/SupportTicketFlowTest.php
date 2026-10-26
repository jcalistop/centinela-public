<?php

namespace Tests\Feature\Support;

use App\Enums\SupportTicketArea;
use App\Enums\SupportTicketStatus;
use App\Mail\SupportTicketNotificationMail;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketReceiver;
use App\Models\User;
use App\Models\Year;
use App\Support\Configuration\ConfigurationService;
use App\Support\Voyager\BrowseSoporteVoyagerMenu;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Setting;
use Tests\TestCase;

class SupportTicketFlowTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $cgRole;

    private User $admin;

    private User $controlGestionSs1;

    private User $controlGestionSs2;

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
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Servicio de Salud Dos',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->attachBrowseAdminRole($this->adminRole);
        $this->attachBrowseAdminRole($this->cgRole);

        $this->admin = User::factory()->create(['role_id' => $this->adminRole->id, 'cen_hie_dependency_id' => 1]);

        $this->controlGestionSs1 = User::factory()->create(['role_id' => $this->cgRole->id, 'cen_hie_dependency_id' => 1]);

        $this->controlGestionSs2 = User::factory()->create(['role_id' => $this->cgRole->id, 'cen_hie_dependency_id' => 2]);

        BrowseSoporteVoyagerMenu::syncRoleGrants();
    }

    public function test_browse_soporte_permission_linked_to_control_gestion(): void
    {
        $permId = Permission::query()->where('key', 'browse_soporte')->value('id');
        $this->assertNotNull($permId);

        $this->assertTrue(DB::table('permission_role')
            ->where('role_id', $this->cgRole->id)
            ->where('permission_id', (int) $permId)
            ->exists());
    }

    private function attachBrowseAdminRole(Role $role): void
    {
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    private function upsertSupportSetting(string $value): void
    {
        Setting::unguarded(fn () => Setting::updateOrCreate(
            ['key' => 'module.soporte_enabled'],
            [
                'display_name' => 'module.soporte_enabled',
                'value' => $value,
                'type' => 'radio_btn',
                'group' => 'module',
                'order' => 1,
            ]
        ));

        $config = $this->app->make(ConfigurationService::class);
        $config->invalidate();
        $config->overrideConfig();
    }

    public function test_support_module_returns_403_when_global_setting_is_off(): void
    {
        $this->upsertSupportSetting('Off');

        $this->actingAs($this->controlGestionSs1)
            ->get(route('centinela.support.index'))
            ->assertForbidden();
    }

    public function test_support_module_uses_database_setting_when_env_flag_is_disabled(): void
    {
        Config::set('centinela.support.enabled', false);
        $this->upsertSupportSetting('On');

        $this->actingAs($this->controlGestionSs1)
            ->get(route('centinela.support.index'))
            ->assertOk();
    }

    public function test_admin_create_ticket_form_lists_only_enabled_servicios(): void
    {
        // Deshabilitar SS 2 via BD
        CenHieDependency::query()->where('id', 2)->update(['habilitado' => false]);

        $response = $this->actingAs($this->admin)
            ->get(route('centinela.support.create'));

        $response->assertOk();
        $response->assertSee('Servicio de Salud Uno', false);
        $response->assertDontSee('Servicio de Salud Dos', false);
    }

    public function test_create_form_renders_requester_select2_picker_for_admin_and_control_gestion(): void
    {
        $adminResponse = $this->actingAs($this->admin)
            ->get(route('centinela.support.create'));

        $adminResponse->assertOk();
        $adminResponse->assertSee('Crear ticket a nombre de', false);
        $adminResponse->assertSee('id="requester_user_id"', false);
        $adminResponse->assertSee('id="wrap_requester_user"', false);
        $adminResponse->assertSee('initRequesterSelect2', false);
        $adminResponse->assertSee('dropdownParent', false);
        $adminResponse->assertSee('requester-user-search-muted', false);
        $adminResponse->assertSee('buscar-solicitantes', false);

        $cgResponse = $this->actingAs($this->controlGestionSs1)
            ->get(route('centinela.support.create'));

        $cgResponse->assertOk();
        $cgResponse->assertSee('Crear ticket a nombre de', false);
        $cgResponse->assertSee('id="requester_user_id"', false);
        $cgResponse->assertDontSee('id="cen_hie_dependency_id"', false);
    }

    public function test_create_form_omits_requester_picker_for_referente(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $this->attachBrowseAdminRole($referenteRole);

        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->actingAs($referente)
            ->get(route('centinela.support.create'))
            ->assertOk()
            ->assertDontSee('Crear ticket a nombre de', false)
            ->assertDontSee('id="requester_user_id"', false);
    }

    public function test_admin_can_create_ticket_on_behalf_of_user_in_selected_servicio(): void
    {
        $requester = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'solicitante-admin@example.com',
        ]);

        $payload = [
            'requester_user_id' => $requester->id,
            'subject' => 'Ticket delegado por admin',
            'body' => 'Cargado por mesa de ayuda.',
            'area' => SupportTicketArea::Gestion->value,
            'year_id' => null,
            'indicator_type_id' => null,
            'cen_hie_dependency_id' => 1,
        ];

        $this->actingAs($this->admin)
            ->post(route('centinela.support.store'), $payload)
            ->assertRedirect();

        $ticket = SupportTicket::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $ticket->cen_hie_dependency_id);
        $this->assertSame($requester->id, $ticket->created_by_user_id);
    }

    public function test_admin_cannot_store_ticket_for_servicio_not_in_enabled_list(): void
    {
        // Deshabilitar SS 2 via BD
        CenHieDependency::query()->where('id', 2)->update(['habilitado' => false]);

        $payload = [
            'subject' => 'Ticket fuera de lista habilitados',
            'body' => 'Cuerpo mínimo',
            'area' => SupportTicketArea::Gestion->value,
            'year_id' => null,
            'indicator_type_id' => null,
            'cen_hie_dependency_id' => 2,
        ];

        $this->actingAs($this->admin)
            ->post(route('centinela.support.store'), $payload)
            ->assertSessionHasErrors('cen_hie_dependency_id');
    }

    public function test_control_gestion_can_create_ticket(): void
    {
        $payload = [
            'subject' => 'Problema acceso',
            'body' => 'No puedo cargar datos.',
            'area' => SupportTicketArea::Gestion->value,
            'year_id' => null,
            'indicator_type_id' => null,
        ];

        $response = $this->actingAs($this->controlGestionSs1)->post(route('centinela.support.store'), $payload);
        $response->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame(1, $ticket->cen_hie_dependency_id);
        $this->assertSame($this->controlGestionSs1->id, $ticket->created_by_user_id);
    }

    public function test_control_gestion_can_create_ticket_with_optional_attachment(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('captura.png', 100, 'image/png');

        $payload = [
            'subject' => 'Error con evidencia',
            'body' => 'Adjunto captura de pantalla.',
            'area' => SupportTicketArea::Tecnico->value,
            'year_id' => null,
            'indicator_type_id' => null,
            'attachment' => $file,
        ];

        $this->actingAs($this->controlGestionSs1)
            ->post(route('centinela.support.store'), $payload)
            ->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertIsArray($ticket->attachment);
        $this->assertSame('captura.png', $ticket->attachment['name'] ?? null);
        $this->assertNotEmpty($ticket->attachment['path'] ?? null);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->assertExists($ticket->attachment['path']);

        $this->actingAs($this->controlGestionSs1)
            ->get(route('centinela.support.attachment.download', $ticket))
            ->assertOk()
            ->assertDownload('captura.png');
    }

    public function test_control_gestion_can_create_ticket_on_behalf_of_user_same_ss(): void
    {
        $requester = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'solicitante@example.com',
        ]);

        $payload = [
            'requester_user_id' => $requester->id,
            'subject' => 'Solicitud por otra vía',
            'body' => 'Me llegó por WhatsApp.',
            'area' => SupportTicketArea::Gestion->value,
            'year_id' => null,
            'indicator_type_id' => null,
        ];

        $this->actingAs($this->controlGestionSs1)
            ->post(route('centinela.support.store'), $payload)
            ->assertRedirect();

        $ticket = SupportTicket::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $ticket->cen_hie_dependency_id);
        $this->assertSame($requester->id, $ticket->created_by_user_id);
    }

    public function test_control_gestion_cannot_create_ticket_on_behalf_of_user_other_ss(): void
    {
        $requester = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 2,
            'email' => 'otro-ss@example.com',
        ]);

        $payload = [
            'requester_user_id' => $requester->id,
            'subject' => 'Solicitud cruzada',
            'body' => 'No debería poder.',
            'area' => SupportTicketArea::Gestion->value,
            'year_id' => null,
            'indicator_type_id' => null,
        ];

        $this->actingAs($this->controlGestionSs1)
            ->post(route('centinela.support.store'), $payload)
            ->assertSessionHasErrors('cen_hie_dependency_id');
    }

    public function test_control_gestion_cannot_view_ticket_from_other_ss(): void
    {
        $ticketSs2 = SupportTicket::factory()->create([
            'cen_hie_dependency_id' => 2,
            'created_by_user_id' => $this->controlGestionSs2->id,
            'area' => SupportTicketArea::Gestion,
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticketSs2->id,
            'user_id' => $this->controlGestionSs2->id,
            'body' => 'Mensaje inicial',
        ]);

        $this->actingAs($this->controlGestionSs1)
            ->get(route('centinela.support.show', $ticketSs2))
            ->assertForbidden();
    }

    public function test_creator_message_on_resolved_reopens_ticket_to_in_progress(): void
    {
        $creator = $this->controlGestionSs1;

        $ticket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Resolved,
            'resolved_at' => now()->subHour(),
            'created_by_user_id' => $creator->id,
            'cen_hie_dependency_id' => 1,
            'area' => SupportTicketArea::Gestion,
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $creator->id,
            'body' => 'Mensaje inicial',
        ]);

        $this->actingAs($creator)->post(route('centinela.support.messages.store', $ticket), [
            'body' => 'Aún tengo una duda tras el cierre.',
        ]);

        $ticket->refresh();
        $this->assertSame(SupportTicketStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_control_gestion_cannot_promote_github_issue(): void
    {
        Config::set('centinela.github_issues.enabled', true);
        Config::set('centinela.github_issues.repo', 'demo/demo');
        Config::set('centinela.github_issues.token', 'dummy');

        $ticket = SupportTicket::factory()->create([
            'created_by_user_id' => $this->controlGestionSs1->id,
            'cen_hie_dependency_id' => 1,
            'area' => SupportTicketArea::Gestion,
        ]);

        $this->actingAs($this->controlGestionSs1)
            ->post(route('centinela.support.github.store', $ticket))
            ->assertForbidden();
    }

    public function test_github_promotion_forbidden_when_github_integration_disabled(): void
    {
        Config::set('centinela.github_issues.enabled', false);

        $ticket = SupportTicket::factory()->create([
            'created_by_user_id' => $this->controlGestionSs1->id,
            'cen_hie_dependency_id' => 1,
            'area' => SupportTicketArea::Gestion,
        ]);

        $this->actingAs($this->admin)
            ->post(route('centinela.support.github.store', $ticket))
            ->assertForbidden();
    }

    public function test_github_second_promotion_does_not_call_github_api(): void
    {
        Http::fake([
            'https://api.github.com/repos/fake-owner/fake-repo/issues' => Http::response([
                'number' => 302,
                'html_url' => 'https://github.com/fake-owner/fake-repo/issues/302',
            ], 201),
        ]);

        Config::set('centinela.github_issues.enabled', true);
        Config::set('centinela.github_issues.repo', 'fake-owner/fake-repo');
        Config::set('centinela.github_issues.token', 'ghs_test_placeholder');

        $ticket = SupportTicket::factory()->create([
            'created_by_user_id' => $this->controlGestionSs1->id,
            'cen_hie_dependency_id' => 1,
            'area' => SupportTicketArea::Gestion,
        ]);

        $this->actingAs($this->admin)
            ->post(route('centinela.support.github.store', $ticket))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(302, $ticket->github_issue_number);

        $this->actingAs($this->admin)
            ->post(route('centinela.support.github.store', $ticket))
            ->assertRedirect();

        $this->assertCount(1, Http::recorded());
    }

    public function test_close_resolved_command_moves_stale_resolved_to_closed(): void
    {
        config(['centinela.support.close_resolved_days' => 5]);

        $creator = $this->controlGestionSs1;

        $ticket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Resolved,
            'resolved_at' => now()->subDays(6),
            'created_by_user_id' => $creator->id,
            'cen_hie_dependency_id' => 1,
            'area' => SupportTicketArea::Gestion,
        ]);

        SupportTicketMessage::unguarded(function () use ($ticket, $creator): void {
            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $creator->id,
                'body' => 'Inicial',
                'created_at' => $ticket->resolved_at->copy()->subDay(),
                'updated_at' => $ticket->resolved_at->copy()->subDay(),
            ]);
        });

        $days = (int) config('centinela.support.close_resolved_days', 5);

        $this->assertSame(
            1,
            SupportTicket::query()
                ->where('status', SupportTicketStatus::Resolved->value)
                ->whereNotNull('resolved_at')
                ->where('resolved_at', '<=', now()->subDays($days))
                ->count()
        );

        $this->artisan('centinela:support-close-resolved')->assertSuccessful();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatus::Closed, $ticket->status);
    }

    public function test_status_change_sends_notification_to_receivers(): void
    {
        Mail::fake();

        $creator = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'creator@example.com',
        ]);

        $receiver = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'receiver@example.com',
        ]);

        SupportTicketReceiver::unguarded(fn () => SupportTicketReceiver::query()->create([
            'user_id' => $receiver->id,
            'area' => SupportTicketArea::Gestion->value,
            'scope' => SupportTicketReceiver::SCOPE_SS,
            'cen_hie_dependency_id' => 1,
            'enabled' => true,
            'is_primary' => false,
        ]));

        $ticket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'cen_hie_dependency_id' => 1,
            'area' => SupportTicketArea::Gestion,
            'created_by_user_id' => $creator->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('centinela.support.status.update', $ticket), [
                'status' => SupportTicketStatus::InProgress->value,
                'comment' => 'Cambiando a en curso.',
                'is_internal' => '0',
            ])
            ->assertRedirect();

        Mail::assertQueued(SupportTicketNotificationMail::class, function (SupportTicketNotificationMail $mail) use ($receiver): bool {
            return $mail->hasTo($receiver->email)
                && str_contains($mail->emailSubjectLine, 'Estado cambiado');
        });

        Mail::assertQueued(SupportTicketNotificationMail::class, function (SupportTicketNotificationMail $mail) use ($creator): bool {
            return $mail->hasTo($creator->email)
                && str_contains($mail->emailSubjectLine, 'Estado cambiado');
        });
    }

    public function test_assignment_change_sends_notification_to_receivers(): void
    {
        Mail::fake();

        $creator = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'assign-creator@example.com',
        ]);

        $receiver = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'assign-rec@example.com',
        ]);

        SupportTicketReceiver::unguarded(fn () => SupportTicketReceiver::query()->create([
            'user_id' => $receiver->id,
            'area' => SupportTicketArea::Gestion->value,
            'scope' => SupportTicketReceiver::SCOPE_SS,
            'cen_hie_dependency_id' => 1,
            'enabled' => true,
            'is_primary' => false,
        ]));

        $ticket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'cen_hie_dependency_id' => 1,
            'assigned_to_user_id' => null,
            'area' => SupportTicketArea::Gestion,
            'created_by_user_id' => $creator->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('centinela.support.assign.update', $ticket), [
                'assigned_to_user_id' => $this->admin->id,
                'comment' => 'Asignado.',
                'is_internal' => '0',
            ])
            ->assertRedirect();

        Mail::assertQueued(SupportTicketNotificationMail::class, function (SupportTicketNotificationMail $mail) use ($receiver): bool {
            return $mail->hasTo($receiver->email)
                && str_contains($mail->emailSubjectLine, 'Asignación actualizada');
        });

        Mail::assertQueued(SupportTicketNotificationMail::class, function (SupportTicketNotificationMail $mail) use ($creator): bool {
            return $mail->hasTo($creator->email)
                && str_contains($mail->emailSubjectLine, 'Asignación actualizada');
        });
    }

    public function test_message_sends_notification_to_ticket_creator(): void
    {
        Mail::fake();

        $creator = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'msg-creator@example.com',
        ]);

        $ticket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'cen_hie_dependency_id' => 1,
            'area' => SupportTicketArea::Gestion,
            'created_by_user_id' => $creator->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('centinela.support.messages.store', $ticket), [
                'body' => 'Respuesta del operador',
            ])
            ->assertRedirect();

        Mail::assertQueued(SupportTicketNotificationMail::class, function (SupportTicketNotificationMail $mail) use ($creator): bool {
            return $mail->hasTo($creator->email)
                && str_contains($mail->emailSubjectLine, 'Nuevo mensaje');
        });

        Mail::assertQueued(SupportTicketNotificationMail::class, function (SupportTicketNotificationMail $mail) use ($creator): bool {
            return $mail->hasTo($creator->email)
                && $mail->kind === SupportTicketNotificationMail::KIND_MESSAGE;
        });
    }

    public function test_internal_status_change_does_not_send_notification(): void
    {
        Mail::fake();

        $receiver = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'no-mail@example.com',
        ]);

        SupportTicketReceiver::unguarded(fn () => SupportTicketReceiver::query()->create([
            'user_id' => $receiver->id,
            'area' => SupportTicketArea::Gestion->value,
            'scope' => SupportTicketReceiver::SCOPE_SS,
            'cen_hie_dependency_id' => 1,
            'enabled' => true,
            'is_primary' => false,
        ]));

        $ticket = SupportTicket::factory()->create([
            'status' => SupportTicketStatus::Open,
            'cen_hie_dependency_id' => 1,
            'area' => SupportTicketArea::Gestion,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('centinela.support.status.update', $ticket), [
                'status' => SupportTicketStatus::InProgress->value,
                'is_internal' => '1',
            ])
            ->assertRedirect();

        Mail::assertNothingQueued();
    }
}
