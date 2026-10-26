<?php

namespace Tests\Feature\Cen;

use App\Http\Controllers\Voyager\CenCenterController;
use App\Models\CenCenterNote;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Services\Cen\CenCenterBitacoraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class CenCenterBitacoraTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn (): ?bool => true);
    }

    public function test_store_note_action_marks_pending_task(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => '24',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center = $this->createMinimalCenCenter(['tarea_pendiente' => false]);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->role()->associate($role)->save();

        $this->actingAs($user);

        $response = app(CenCenterController::class)->storeNote(
            Request::create(
                "/admin/establecimientos/{$center->id}/notes",
                'POST',
                ['body' => 'Validar datos con API', 'tarea_pendiente' => '1'],
            ),
            $center->id,
        );

        $this->assertTrue($response->isRedirect());

        $center->refresh();

        $this->assertTrue($center->tarea_pendiente);
        $this->assertSame(1, CenCenterNote::query()->count());
        $this->assertSame(
            CenCenterBitacoraService::NOTE_KIND_PENDING_MARKED,
            CenCenterNote::query()->value('note_kind')
        );
    }

    public function test_control_gestion_cannot_store_note_or_pending_task(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => '24',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center = $this->createMinimalCenCenter(['tarea_pendiente' => false]);

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->role()->associate($role)->save();

        $this->actingAs($user);

        try {
            app(CenCenterController::class)->storeNote(
                Request::create(
                    "/admin/establecimientos/{$center->id}/notes",
                    'POST',
                    ['body' => 'No debería guardar', 'tarea_pendiente' => '1'],
                ),
                $center->id,
            );
            $this->fail('Se esperaba HTTP 403 para control-gestion.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $center->refresh();

        $this->assertFalse($center->tarea_pendiente);
        $this->assertSame(0, CenCenterNote::query()->count());
    }
}
