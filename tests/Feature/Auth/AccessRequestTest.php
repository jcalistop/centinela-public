<?php

namespace Tests\Feature\Auth;

use App\Enums\SupportTicketArea;
use App\Mail\AccessRequestSubmitted;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\SupportTicketReceiver;
use App\Models\User;
use App\Support\Auth\AccessRequestServicioSalud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccessRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $controlGestion;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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
            'habilitado' => false,
        ]));

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'cg-ss01@example.test',
        ]);
    }

    public function test_create_form_is_public_and_shows_access_copy(): void
    {
        $this->get(route('centinela.access-request.create'))
            ->assertOk()
            ->assertSee('Solicitar acceso a Centinela', false)
            ->assertSee('recuperación de contraseña', false)
            ->assertSee('SS01', false)
            ->assertSee('Otro servicio de salud', false)
            ->assertDontSee('No habilitados en la plataforma', false);
    }

    public function test_store_sends_mail_to_primary_gestion_receiver(): void
    {
        $this->createPrimaryReceiver($this->controlGestion, 1);

        $payload = $this->validPayload();

        $this->post(route('centinela.access-request.store'), $payload)
            ->assertRedirect(route('centinela.access-request.create'))
            ->assertSessionHas('access_request_success');

        Mail::assertQueued(AccessRequestSubmitted::class, function (AccessRequestSubmitted $mail): bool {
            return $mail->hasTo('cg-ss01@example.test')
                && $mail->payload['nombre'] === 'Ana Pérez'
                && $mail->servicioSalud?->id === 1;
        });
    }

    public function test_store_falls_back_to_contact_mail_to_without_primary_receiver(): void
    {
        config()->set('centinela.contact_mail_to', 'admin-fallback@example.test');

        $payload = $this->validPayload();

        $this->post(route('centinela.access-request.store'), $payload)
            ->assertRedirect(route('centinela.access-request.create'))
            ->assertSessionHas('access_request_success');

        Mail::assertQueued(AccessRequestSubmitted::class, function (AccessRequestSubmitted $mail): bool {
            return $mail->hasTo('admin-fallback@example.test');
        });
    }

    public function test_store_accepts_disabled_servicio_and_sends_to_admin_fallback(): void
    {
        config()->set('centinela.contact_mail_to', 'admin-fallback@example.test');

        $payload = $this->validPayload(['cen_hie_dependency_id' => 2]);

        $this->post(route('centinela.access-request.store'), $payload)
            ->assertRedirect(route('centinela.access-request.create'))
            ->assertSessionHas('access_request_success');

        Mail::assertQueued(AccessRequestSubmitted::class, function (AccessRequestSubmitted $mail): bool {
            return $mail->hasTo('admin-fallback@example.test')
                && $mail->servicioSaludDisplay === 'SS02 — Servicio de Salud Dos';
        });
    }

    public function test_store_accepts_otro_servicio_and_sends_to_admin_fallback(): void
    {
        config()->set('centinela.contact_mail_to', 'admin-fallback@example.test');

        $payload = $this->validPayload([
            'cen_hie_dependency_id' => AccessRequestServicioSalud::OTRO_ID,
            'servicio_salud_nombre' => 'Servicio de Salud Metropolitano Sur',
        ]);

        $this->post(route('centinela.access-request.store'), $payload)
            ->assertRedirect(route('centinela.access-request.create'))
            ->assertSessionHas('access_request_success');

        Mail::assertQueued(AccessRequestSubmitted::class, function (AccessRequestSubmitted $mail): bool {
            return $mail->hasTo('admin-fallback@example.test')
                && $mail->servicioSalud === null
                && $mail->servicioSaludDisplay === 'Servicio de Salud Metropolitano Sur';
        });
    }

    public function test_store_requires_servicio_nombre_when_otro_selected(): void
    {
        $payload = $this->validPayload([
            'cen_hie_dependency_id' => AccessRequestServicioSalud::OTRO_ID,
            'servicio_salud_nombre' => '',
        ]);

        $this->post(route('centinela.access-request.store'), $payload)
            ->assertSessionHasErrors('servicio_salud_nombre');

        Mail::assertNothingQueued();
    }

    public function test_store_shows_error_when_no_recipient_configured(): void
    {
        config()->set('centinela.contact_mail_to', '');

        $this->post(route('centinela.access-request.store'), $this->validPayload())
            ->assertRedirect(route('centinela.access-request.create'))
            ->assertSessionHas('access_request_error');

        Mail::assertNothingQueued();
    }

    public function test_cannot_enable_servicio_without_primary_gestion_receiver(): void
    {
        $ss = CenHieDependency::query()->findOrFail(2);
        $ss->habilitado = false;
        $ss->save();

        $this->expectException(ValidationException::class);

        $ss->habilitado = true;
        $ss->save();
    }

    public function test_can_enable_servicio_when_primary_gestion_receiver_exists(): void
    {
        $this->createPrimaryReceiver($this->controlGestion, 2);

        $ss = CenHieDependency::query()->findOrFail(2);
        $ss->habilitado = true;
        $ss->save();

        $this->assertTrue($ss->fresh()->habilitado);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ana Pérez',
            'email' => 'solicitante@example.test',
            'telefono' => '+56 9 1234 5678',
            'cargo' => 'Analista',
            'cen_hie_dependency_id' => 1,
            'mensaje' => 'Necesito acceso para cargar indicadores.',
        ], $overrides);
    }

    private function createPrimaryReceiver(User $user, int $ssId): void
    {
        SupportTicketReceiver::query()->create([
            'scope' => SupportTicketReceiver::SCOPE_SS,
            'cen_hie_dependency_id' => $ssId,
            'area' => SupportTicketArea::Gestion,
            'user_id' => $user->id,
            'is_primary' => true,
            'enabled' => true,
        ]);
    }
}
