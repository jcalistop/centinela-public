<?php

namespace Tests\Feature\Messaging;

use App\Enums\MessagingAudience;
use App\Livewire\Messaging\MessagingCampaignIndex;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Messaging\MessagingCampaignLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class VoyagerMessagingCampaignIndexTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private User $admin;

    private User $controlGestion;

    private User $referente;

    private int $ssId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.messaging.wave_size' => 50,
            'centinela.messaging.wave_delay_seconds' => 300,
            'mail.from.address' => 'centinela@minsal.cl',
            'mail.from.name' => 'Sistema Centinela',
        ]);

        Year::unguarded(fn (): Year => Year::create(['ano' => 2026, 'default' => 1]));

        $this->ssId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-MSG-IDX-1',
            'nombre' => 'SS Mensajería Index',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $refRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        foreach ([$adminRole, $cgRole, $refRole] as $role) {
            $this->grantBrowseAdminPermissionToRole($role);
        }

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'name' => 'Admin Listado',
            'email' => 'admin.idx.msg@example.test',
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'name' => 'CG Listado',
            'email' => 'cg.idx.msg@example.test',
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->referente = User::factory()->create([
            'role_id' => $refRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'email' => 'ref.idx.msg@example.test',
        ]);
        $this->referente->role()->associate($refRole)->save();
    }

    public function test_admin_sees_campaign_from_other_sender_and_cg_does_not(): void
    {
        Queue::fake();

        app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::SpecificUsers,
            'user_ids' => [$this->referente->id],
            'subject' => 'Asunto listado visible admin',
            'body_html' => '<p>Cuerpo listado</p>',
        ]);

        Livewire::actingAs($this->admin)
            ->test(MessagingCampaignIndex::class)
            ->assertOk()
            ->assertSee('Asunto listado visible admin')
            ->assertSee('Admin Listado');

        Livewire::actingAs($this->controlGestion)
            ->test(MessagingCampaignIndex::class)
            ->assertOk()
            ->assertDontSee('Asunto listado visible admin');
    }

    public function test_control_gestion_detail_of_foreign_campaign_is_404(): void
    {
        Queue::fake();

        $campaign = app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::SpecificUsers,
            'user_ids' => [$this->referente->id],
            'subject' => 'Ajena al CG',
            'body_html' => '<p>secreto</p>',
        ]);

        Livewire::actingAs($this->controlGestion)
            ->test(MessagingCampaignIndex::class)
            ->call('openDetail', $campaign->id)
            ->assertNotFound();
    }

    public function test_detail_iframe_uses_sandbox_and_does_not_print_raw_html(): void
    {
        Queue::fake();

        $campaign = app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::SpecificUsers,
            'user_ids' => [$this->referente->id],
            'subject' => 'Detalle iframe',
            'body_html' => '<p>Cuerpo seguro</p>',
        ]);

        $html = Livewire::actingAs($this->admin)
            ->test(MessagingCampaignIndex::class)
            ->call('openDetail', $campaign->id)
            ->assertSet('showDetail', true)
            ->html();

        $this->assertStringContainsString('sandbox=""', $html);
        $this->assertStringContainsString('srcdoc=', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('{!!', $html);
    }

    public function test_search_filters_by_subject(): void
    {
        Queue::fake();

        app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::SpecificUsers,
            'user_ids' => [$this->referente->id],
            'subject' => 'Alfa visible',
            'body_html' => '<p>a</p>',
        ]);
        app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::SpecificUsers,
            'user_ids' => [$this->referente->id],
            'subject' => 'Beta oculta',
            'body_html' => '<p>b</p>',
        ]);

        Livewire::actingAs($this->admin)
            ->test(MessagingCampaignIndex::class)
            ->set('search', 'Alfa')
            ->assertSee('Alfa visible')
            ->assertDontSee('Beta oculta');
    }

    public function test_referente_cannot_open_index(): void
    {
        Livewire::actingAs($this->referente)
            ->test(MessagingCampaignIndex::class)
            ->assertForbidden();
    }
}
