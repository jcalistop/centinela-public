<?php

namespace Tests\Unit\Services\Notifications;

use App\Enums\Indicators\IndicatorActivityAction;
use App\Enums\Indicators\IndicatorModuleType;
use App\Models\IndicatorActivity;
use App\Models\User;
use App\Services\Notifications\IndicatorNoteMailDataFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorNoteMailDataFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_mail_data_from_manual_note_activity(): void
    {
        $author = User::factory()->create(['name' => 'Ana Admin']);

        $activity = IndicatorActivity::query()->create([
            'indicator_type' => IndicatorModuleType::Metas,
            'indicator_id' => 99,
            'action' => IndicatorActivityAction::ManualNote,
            'description' => 'Seguimiento de avance trimestral.',
            'metadata' => ['cen_hie_dependency_id' => null],
            'user_id' => $author->id,
            'created_at' => now()->setDate(2026, 6, 16)->setTime(14, 30),
        ]);

        $mailData = IndicatorNoteMailDataFactory::fromActivity($activity);

        $this->assertSame('Metas', $mailData->moduleLabel);
        $this->assertSame('Seguimiento de avance trimestral.', $mailData->noteText);
        $this->assertSame('Ana Admin', $mailData->authorName);
        $this->assertSame('16/06/2026 14:30', $mailData->registeredAt);
        $this->assertNotSame('', $mailData->bitacoraUrl);
    }
}
