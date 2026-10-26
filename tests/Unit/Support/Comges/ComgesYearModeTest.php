<?php

namespace Tests\Unit\Support\Comges;

use App\Models\Year;
use App\Support\Comges\ComgesYearMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComgesYearModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_score_scale_from_year_2025(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));

        $this->assertTrue(ComgesYearMode::usesScoreScale($year));
    }

    public function test_does_not_use_score_scale_before_2025(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));

        $this->assertFalse(ComgesYearMode::usesScoreScale($year));
    }

    public function test_current_uses_score_scale_reads_session_indicator_year(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));

        $this->withSession(['indicator_year' => $year]);

        $this->assertFalse(ComgesYearMode::currentUsesScoreScale());
    }

    public function test_apelacion_actions_hide_sensitivity_and_evaluations_only_for_year_2025(): void
    {
        $year2025 = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));
        $year2026 = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 0]));

        $this->assertTrue(ComgesYearMode::apelacionActionsHideSensitivityAndEvaluations($year2025));
        $this->assertFalse(ComgesYearMode::apelacionActionsHideSensitivityAndEvaluations($year2026));
        $this->assertFalse(ComgesYearMode::apelacionActionsHideSensitivityAndEvaluations(null));
    }

    public function test_uses_monitoring_cutoff_ui_from_year_2026(): void
    {
        $year2025 = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));
        $year2026 = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 0]));

        $this->assertFalse(ComgesYearMode::usesMonitoringCutoffUi($year2025));
        $this->assertTrue(ComgesYearMode::usesMonitoringCutoffUi($year2026));
    }
}
