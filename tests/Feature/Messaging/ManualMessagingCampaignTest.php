<?php

namespace Tests\Feature\Messaging;

use App\Enums\MessagingAudience;
use App\Enums\MessagingCampaignStatus;
use App\Enums\MessagingRecipientStatus;
use App\Filament\Configuracion\Pages\ComposeMessagingCampaign as ConfigCompose;
use App\Jobs\SendMessagingCampaignWaveJob;
use App\Mail\ManualMessagingMail;
use App\Models\MessagingCampaign;
use App\Models\MessagingCampaignRecipient;
use App\Models\Role;
use App\Models\User;
use App\Services\Messaging\MessagingCampaignLauncher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ManualMessagingCampaignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $controlGestion;

    private User $referente;

    private User $referenteOtherSs;

    private int $ssId;

    private int $otherSsId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.filament.enabled' => true,
            'centinela.filament.messaging_enabled' => true,
            'centinela.messaging.wave_size' => 2,
            'centinela.messaging.wave_delay_seconds' => 300,
            'mail.from.address' => 'centinela@minsal.cl',
            'mail.from.name' => 'Sistema Centinela',
        ]);

        $this->ssId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-MSG-1',
            'nombre' => 'SS Mensajería 1',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->otherSsId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-MSG-2',
            'nombre' => 'SS Mensajería 2',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $refRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $vizRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'unidad' => 'Unidad TI',
            'name' => 'Admin Emisor',
            'email' => 'admin.msg@example.test',
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'unidad' => 'CG Local',
            'name' => 'CG Emisor',
            'email' => 'cg.msg@example.test',
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->referente = User::factory()->create([
            'role_id' => $refRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'email' => 'ref1@example.test',
        ]);
        $this->referente->role()->associate($refRole)->save();

        $this->referenteOtherSs = User::factory()->create([
            'role_id' => $refRole->id,
            'cen_hie_dependency_id' => $this->otherSsId,
            'email' => 'ref2@example.test',
        ]);
        $this->referenteOtherSs->role()->associate($refRole)->save();

        User::factory()->create([
            'role_id' => $vizRole->id,
            'cen_hie_dependency_id' => $this->ssId,
            'email' => 'viz@example.test',
        ]);
    }

    public function test_referente_cannot_access_configuracion_mensajeria(): void
    {
        Filament::setCurrentPanel('configuracion');

        $this->actingAs($this->referente)
            ->get('/configuracion/mensajeria')
            ->assertForbidden();
    }

    public function test_admin_forbidden_when_messaging_flag_off(): void
    {
        config(['centinela.filament.messaging_enabled' => false]);
        Filament::setCurrentPanel('configuracion');

        $this->actingAs($this->admin)
            ->get('/configuracion/mensajeria')
            ->assertForbidden();
    }

    public function test_admin_can_view_compose_page_when_flag_on(): void
    {
        Filament::setCurrentPanel('configuracion');

        $this->actingAs($this->admin)
            ->get('/configuracion/mensajeria')
            ->assertOk()
            ->assertSee('Mensajería enviada');
    }

    public function test_admin_sees_campaign_in_list_under_form(): void
    {
        Queue::fake();
        Filament::setCurrentPanel('configuracion');

        app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::SpecificUsers,
            'user_ids' => [$this->referente->id],
            'subject' => 'Asunto listado visible',
            'body_html' => '<p>x</p>',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ConfigCompose::class)
            ->assertOk()
            ->assertCanSeeTableRecords(MessagingCampaign::query()->get())
            ->assertSee('Asunto listado visible');
    }

    public function test_control_gestion_can_view_servicio_salud_compose(): void
    {
        Filament::setCurrentPanel('servicio-salud');

        $this->actingAs($this->controlGestion)
            ->get('/servicio-salud/mensajeria')
            ->assertOk();
    }

    public function test_admin_launches_all_users_campaign_and_queues_wave_job(): void
    {
        Queue::fake();

        $campaign = app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::AllUsers,
            'subject' => 'Aviso general',
            'body_html' => '<p>Hola <strong>mundo</strong></p>',
        ]);

        $this->assertSame(MessagingCampaignStatus::Queued, $campaign->status);
        $this->assertGreaterThanOrEqual(4, $campaign->recipients_total);
        $this->assertSame(
            $campaign->recipients_total,
            MessagingCampaignRecipient::query()->where('messaging_campaign_id', $campaign->id)->count()
        );

        Queue::assertPushed(SendMessagingCampaignWaveJob::class, function (SendMessagingCampaignWaveJob $job) use ($campaign): bool {
            return $job->campaignId === $campaign->id;
        });
    }

    public function test_wave_job_sends_mail_with_reply_to_and_footer_and_schedules_next_wave(): void
    {
        Mail::fake();
        Queue::fake();

        config(['centinela.messaging.wave_size' => 2]);

        $campaign = app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::AllUsers,
            'subject' => 'Ola test',
            'body_html' => '<p>Cuerpo</p>',
        ]);

        Queue::assertPushed(SendMessagingCampaignWaveJob::class);

        (new SendMessagingCampaignWaveJob($campaign->id))->handle();

        Mail::assertSent(ManualMessagingMail::class, 2);

        Mail::assertSent(ManualMessagingMail::class, function (ManualMessagingMail $mail): bool {
            $envelope = $mail->envelope();

            return $envelope->from->address === 'centinela@minsal.cl'
                && collect($envelope->replyTo)->contains(fn ($a) => $a->address === 'admin.msg@example.test');
        });

        $campaign->refresh();
        $this->assertSame(2, $campaign->recipients_sent);
        $this->assertTrue(
            MessagingCampaignRecipient::query()
                ->where('messaging_campaign_id', $campaign->id)
                ->where('status', MessagingRecipientStatus::Queued)
                ->exists()
        );

        Queue::assertPushed(SendMessagingCampaignWaveJob::class, 2);
    }

    public function test_control_gestion_cannot_target_other_health_service(): void
    {
        $this->expectException(ValidationException::class);

        app(MessagingCampaignLauncher::class)->launch($this->controlGestion, [
            'audience' => MessagingAudience::HealthServiceAll,
            'cen_hie_dependency_id' => $this->otherSsId,
            'subject' => 'Fuera de SS',
            'body_html' => '<p>x</p>',
        ]);
    }

    public function test_control_gestion_sends_only_own_ss_referentes(): void
    {
        Queue::fake();

        $campaign = app(MessagingCampaignLauncher::class)->launch($this->controlGestion, [
            'audience' => MessagingAudience::HealthServiceReferentes,
            'subject' => 'Solo referentes',
            'body_html' => '<p>Hola referentes</p>',
        ]);

        $emails = MessagingCampaignRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->pluck('email')
            ->all();

        $this->assertSame(['ref1@example.test'], $emails);
    }

    public function test_zero_recipients_does_not_create_campaign(): void
    {
        $emptySs = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-EMPTY',
            'nombre' => 'SS Vacío',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(MessagingCampaignLauncher::class)->launch($this->admin, [
                'audience' => MessagingAudience::HealthServiceReferentes,
                'cen_hie_dependency_id' => $emptySs,
                'subject' => 'Vacío',
                'body_html' => '<p>x</p>',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('audience', $e->errors());
        }

        $this->assertSame(0, MessagingCampaign::query()->count());
    }

    public function test_failed_recipient_does_not_abort_wave(): void
    {
        config(['centinela.messaging.wave_size' => 50]);

        $pendingMail = new class
        {
            public int $calls = 0;

            public function send(object $mailable): void
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new \RuntimeException('SMTP boom');
                }
            }
        };

        Mail::shouldReceive('to')->andReturn($pendingMail);

        $campaign = app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::SpecificUsers,
            'user_ids' => [$this->referente->id, $this->referenteOtherSs->id],
            'subject' => 'Parcial',
            'body_html' => '<p>x</p>',
        ]);

        (new SendMessagingCampaignWaveJob($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame(1, $campaign->recipients_failed);
        $this->assertSame(1, $campaign->recipients_sent);
        $this->assertSame(MessagingCampaignStatus::Completed, $campaign->status);
    }

    public function test_livewire_admin_preview_and_send(): void
    {
        Queue::fake();
        Filament::setCurrentPanel('configuracion');

        Livewire::actingAs($this->admin)
            ->test(ConfigCompose::class)
            ->fillForm([
                'audience' => MessagingAudience::HealthServiceReferentes->value,
                'cen_hie_dependency_id' => $this->ssId,
                'subject' => 'Desde Livewire',
                'body_html' => '<p>Contenido</p>',
            ])
            ->call('previewRecipients')
            ->assertSet('previewCount', 1)
            ->call('sendCampaign')
            ->assertHasNoFormErrors();

        $this->assertSame(1, MessagingCampaign::query()->count());
        Queue::assertPushed(SendMessagingCampaignWaveJob::class);
    }

    public function test_control_gestion_cannot_select_users_outside_own_ss(): void
    {
        $this->expectException(ValidationException::class);

        app(MessagingCampaignLauncher::class)->previewCount($this->controlGestion, [
            'audience' => MessagingAudience::SpecificUsers,
            'user_ids' => [$this->referenteOtherSs->id],
            'subject' => 'No',
            'body_html' => '<p>x</p>',
        ]);
    }

    public function test_admin_cannot_target_disabled_health_service(): void
    {
        $disabledSs = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-OFF',
            'nombre' => 'SS Deshabilitado',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(MessagingCampaignLauncher::class)->launch($this->admin, [
            'audience' => MessagingAudience::HealthServiceAll,
            'cen_hie_dependency_id' => $disabledSs,
            'subject' => 'SS off',
            'body_html' => '<p>x</p>',
        ]);
    }
}
