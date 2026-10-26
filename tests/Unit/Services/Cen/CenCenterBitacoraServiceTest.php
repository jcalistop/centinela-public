<?php

namespace Tests\Unit\Services\Cen;

use App\Models\CenCenterNote;
use App\Models\CenHieDependency;
use App\Models\User;
use App\Services\Cen\CenCenterBitacoraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class CenCenterBitacoraServiceTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    public function test_marks_pending_and_writes_bitacora_entry(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => '24',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center = $this->createMinimalCenCenter(['tarea_pendiente' => false]);
        $user = User::factory()->create();

        $note = app(CenCenterBitacoraService::class)->storeNote(
            $center,
            $user,
            'Seguimiento con SEREMI',
            true,
        );

        $center->refresh();

        $this->assertTrue($center->tarea_pendiente);
        $this->assertSame(CenCenterBitacoraService::NOTE_KIND_PENDING_MARKED, $note->note_kind);
        $this->assertStringContainsString('【Tarea pendiente】', $note->body);
        $this->assertStringContainsString('Seguimiento con SEREMI', $note->body);
    }

    public function test_clears_pending_and_writes_bitacora_entry(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => '24',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center = $this->createMinimalCenCenter(['tarea_pendiente' => true]);
        $user = User::factory()->create();

        $note = app(CenCenterBitacoraService::class)->storeNote(
            $center,
            $user,
            'Resuelto en reunión',
            false,
        );

        $center->refresh();

        $this->assertFalse($center->tarea_pendiente);
        $this->assertSame(CenCenterBitacoraService::NOTE_KIND_PENDING_CLEARED, $note->note_kind);
        $this->assertStringContainsString('dejó de registrar tarea pendiente', $note->body);
    }

    public function test_user_note_without_pending_change(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => '24',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center = $this->createMinimalCenCenter(['tarea_pendiente' => false]);
        $user = User::factory()->create();

        $note = app(CenCenterBitacoraService::class)->storeNote(
            $center,
            $user,
            'Solo comentario',
            false,
        );

        $this->assertSame(CenCenterBitacoraService::NOTE_KIND_USER, $note->note_kind);
        $this->assertSame('Solo comentario', $note->body);
        $this->assertSame(1, CenCenterNote::query()->count());
    }

    public function test_rejects_empty_note_when_pending_unchanged(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => '24',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center = $this->createMinimalCenCenter();
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(CenCenterBitacoraService::class)->storeNote($center, $user, '', false);
    }
}
