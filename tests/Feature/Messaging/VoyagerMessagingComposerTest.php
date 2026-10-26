<?php

namespace Tests\Feature\Messaging;

use App\Enums\MessagingAudience;
use App\Jobs\SendMessagingCampaignWaveJob;
use App\Livewire\Messaging\MessagingCampaignComposer;
use App\Models\MessagingCampaign;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Policies\CustomUserPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class VoyagerMessagingComposerTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private User $admin;

    private User $controlGestion;

    private User $referente;

    private User $referenteOtherSs;

    private User $parametrizador;

    private User $visualizador;

    private int $ssId;

    private int $otherSsId;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.messaging.wave_size' => 2,
            'centinela.messaging.wave_delay_seconds' => 300,
            'mail.from.address' => 'centinela@minsal.cl',
            'mail.from.name' => 'Sistema Centinela',
        ]);

        $this->year = Year::unguarded(fn (): Year => Year::create(['ano' => 2026, 'default' => 1]));

        $this->ssId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-MSG-V-1',
            'nombre' => 'SS Mensajería Voyager 1',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->otherSsId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-MSG-V-2',
            'nombre' => 'SS Mensajería Voyager 2',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $refRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $vizRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);

        foreach ([$adminRole, $cgRole, $refRole, $paramRole, $vizRole] as $role) {
            $this->grantBrowseAdminPermissionToRole($role);
        }

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'unidad' => 'Unidad TI',
            'name' => 'Admin Emisor',
            'email' => 'admin.voyager.msg@example.test',
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'unidad' => 'CG Local',
            'name' => 'CG Emisor',
            'email' => 'cg.voyager.msg@example.test',
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->referente = User::factory()->create([
            'role_id' => $refRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'email' => 'ref.voyager@example.test',
        ]);
        $this->referente->role()->associate($refRole)->save();

        $this->referenteOtherSs = User::factory()->create([
            'role_id' => $refRole->id,
            'cen_hie_dependency_id' => $this->otherSsId,
            'email' => 'ref2.voyager@example.test',
        ]);
        $this->referenteOtherSs->role()->associate($refRole)->save();

        $this->parametrizador = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'email' => 'param.voyager.msg@example.test',
        ]);
        $this->parametrizador->role()->associate($paramRole)->save();

        $this->visualizador = User::factory()->create([
            'role_id' => $vizRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'email' => 'viz.voyager.msg@example.test',
        ]);
        $this->visualizador->role()->associate($vizRole)->save();
    }

    public function test_admin_and_control_gestion_see_composer(): void
    {
        $this->actingAs($this->admin)
            ->get(route('centinela.admin.mensajeria'))
            ->assertOk()
            ->assertSeeLivewire(MessagingCampaignComposer::class)
            ->assertSee('Mensajería enviada')
            ->assertSee('centinela-mensajeria-volver-usuarios', false)
            ->assertSee('Volver a Usuarios')
            ->assertSee(route('voyager.users.index'), false);

        $this->actingAs($this->controlGestion)
            ->get(route('centinela.admin.mensajeria'))
            ->assertOk()
            ->assertSeeLivewire(MessagingCampaignComposer::class);
    }

    public function test_admin_preview_and_send_queues_job_and_resets_form(): void
    {
        Queue::fake();

        Livewire::actingAs($this->admin)
            ->test(MessagingCampaignComposer::class)
            ->set('audience', MessagingAudience::HealthServiceReferentes->value)
            ->set('cen_hie_dependency_id', $this->ssId)
            ->set('subject', 'Desde Voyager')
            ->set('body_html', '<p>Contenido</p>')
            ->call('previewRecipients')
            ->assertSet('previewCount', 1)
            ->call('openConfirm')
            ->assertSet('showConfirm', true)
            ->call('sendCampaign')
            ->assertHasNoErrors()
            ->assertSet('subject', '')
            ->assertSet('body_html', '')
            ->assertSet('previewCount', null);

        $this->assertSame(1, MessagingCampaign::query()->count());
        Queue::assertPushed(SendMessagingCampaignWaveJob::class);
    }

    public function test_preview_zero_recipients_warns_and_does_not_send(): void
    {
        Queue::fake();

        $emptySs = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-V-EMPTY',
            'nombre' => 'SS Vacío Voyager',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(MessagingCampaignComposer::class)
            ->set('audience', MessagingAudience::HealthServiceReferentes->value)
            ->set('cen_hie_dependency_id', $emptySs)
            ->set('subject', 'Vacío')
            ->set('body_html', '<p>x</p>')
            ->call('previewRecipients')
            ->assertSet('previewCount', 0)
            ->assertDispatched('toast', type: 'warning')
            ->call('sendCampaign')
            ->assertSet('previewCount', 0);

        $this->assertSame(0, MessagingCampaign::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_referente_parametrizador_visualizador_get_403_on_http_and_component(): void
    {
        foreach ([$this->referente, $this->parametrizador, $this->visualizador] as $user) {
            $this->actingAs($user)->get(route('centinela.admin.mensajeria'))->assertForbidden();
            Livewire::actingAs($user)->test(MessagingCampaignComposer::class)->assertForbidden();
        }
    }

    public function test_actions_called_directly_by_non_composer_are_forbidden(): void
    {
        foreach ([$this->referente, $this->parametrizador, $this->visualizador] as $user) {
            $this->actingAs($user);

            foreach (['previewRecipients', 'openConfirm', 'closeConfirm', 'sendCampaign'] as $action) {
                try {
                    (new MessagingCampaignComposer)->{$action}();
                    $this->fail("La acción {$action} no debió ejecutarse para un no emisor.");
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode(), $action);
                }
            }
        }
    }

    public function test_control_gestion_cannot_use_all_users_audience(): void
    {
        Queue::fake();

        Livewire::actingAs($this->controlGestion)
            ->test(MessagingCampaignComposer::class)
            ->set('audience', MessagingAudience::AllUsers->value)
            ->set('subject', 'Todos')
            ->set('body_html', '<p>x</p>')
            ->call('previewRecipients')
            ->assertHasErrors(['audience']);

        $this->assertSame(0, MessagingCampaign::query()->count());
    }

    public function test_control_gestion_cannot_target_other_health_service(): void
    {
        Livewire::actingAs($this->controlGestion)
            ->test(MessagingCampaignComposer::class)
            ->set('audience', MessagingAudience::HealthServiceAll->value)
            ->set('cen_hie_dependency_id', $this->otherSsId)
            ->set('subject', 'Fuera de SS')
            ->set('body_html', '<p>x</p>')
            ->call('previewRecipients')
            ->assertHasErrors(['cen_hie_dependency_id']);

        $this->assertSame(0, MessagingCampaign::query()->count());
    }

    public function test_control_gestion_cannot_select_users_outside_own_ss(): void
    {
        Livewire::actingAs($this->controlGestion)
            ->test(MessagingCampaignComposer::class)
            ->set('audience', MessagingAudience::SpecificUsers->value)
            ->set('user_ids', [$this->referenteOtherSs->id])
            ->set('subject', 'Ajeno')
            ->set('body_html', '<p>x</p>')
            ->call('previewRecipients')
            ->assertHasErrors(['user_ids']);

        $this->assertSame(0, MessagingCampaign::query()->count());
    }

    public function test_empty_html_body_does_not_enqueue(): void
    {
        Queue::fake();

        Livewire::actingAs($this->admin)
            ->test(MessagingCampaignComposer::class)
            ->set('audience', MessagingAudience::HealthServiceReferentes->value)
            ->set('cen_hie_dependency_id', $this->ssId)
            ->set('subject', 'Vacío HTML')
            ->set('body_html', '<p><br></p>')
            ->call('sendCampaign')
            ->assertHasErrors(['body_html']);

        $this->assertSame(0, MessagingCampaign::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_admin_enters_voyager_with_filament_messaging_gate_off(): void
    {
        config([
            'centinela.filament.enabled' => false,
            'centinela.filament.messaging_enabled' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('centinela.admin.mensajeria'))
            ->assertOk()
            ->assertSeeLivewire(MessagingCampaignComposer::class);
    }

    public function test_filament_surface_stays_reachable_dual_run(): void
    {
        config([
            'centinela.filament.enabled' => true,
            'centinela.filament.messaging_enabled' => true,
        ]);

        Filament::setCurrentPanel('configuracion');
        $this->actingAs($this->admin)
            ->get('/configuracion/mensajeria')
            ->assertOk();

        Filament::setCurrentPanel('servicio-salud');
        $this->actingAs($this->controlGestion)
            ->get('/servicio-salud/mensajeria')
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('centinela.admin.mensajeria'))->assertRedirect();
    }

    public function test_users_browse_shows_mensajeria_button_for_admin_and_control_gestion(): void
    {
        $this->seedUsersBrowseForMessagingButton();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.index'))
            ->assertOk()
            ->assertSee('id="centinela-users-browse-mensajeria"', false)
            ->assertSee(route('centinela.admin.mensajeria'), false);

        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.index'))
            ->assertOk()
            ->assertSee('id="centinela-users-browse-mensajeria"', false);
    }

    public function test_users_browse_hides_mensajeria_button_for_referente(): void
    {
        $this->seedUsersBrowseForMessagingButton();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.index'))
            ->assertOk()
            ->assertDontSee('id="centinela-users-browse-mensajeria"', false);
    }

    private function seedUsersBrowseForMessagingButton(): void
    {
        foreach ([$this->admin, $this->controlGestion, $this->referente] as $user) {
            $role = $user->role;
            if (! $role instanceof Role) {
                continue;
            }

            $permission = Permission::firstOrCreate(
                ['key' => 'browse_users', 'table_name' => 'users'],
                ['key' => 'browse_users', 'table_name' => 'users'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        DataType::unguarded(function (): void {
            $existing = DataType::query()->where('slug', 'users')->first();
            if ($existing instanceof DataType) {
                $existing->update([
                    'controller' => '\\App\\Http\\Controllers\\Voyager\\CentinelaVoyagerUserController',
                    'policy_name' => CustomUserPolicy::class,
                ]);
                $existing->scope = 'voyagerActorManageable';
                $existing->save();
                $dataTypeId = (int) $existing->getKey();
            } else {
                $dataType = DataType::create([
                    'name' => 'users',
                    'slug' => 'users',
                    'display_name_singular' => 'Usuario',
                    'display_name_plural' => 'Usuarios',
                    'icon' => 'voyager-person',
                    'model_name' => User::class,
                    'policy_name' => CustomUserPolicy::class,
                    'controller' => '\\App\\Http\\Controllers\\Voyager\\CentinelaVoyagerUserController',
                    'generate_permissions' => 1,
                    'server_side' => 0,
                ]);
                $dataType->scope = 'voyagerActorManageable';
                $dataType->save();
                $dataTypeId = (int) $dataType->getKey();
            }

            if (! Schema::hasTable('data_rows')) {
                return;
            }

            DataRow::query()->updateOrInsert(
                ['data_type_id' => $dataTypeId, 'field' => 'name'],
                [
                    'type' => 'text',
                    'display_name' => 'Nombre',
                    'required' => 1,
                    'browse' => 1,
                    'read' => 1,
                    'edit' => 1,
                    'add' => 1,
                    'delete' => 0,
                    'details' => '{}',
                    'order' => 1,
                ],
            );
        });
    }
}
