<?php

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ComgesSemaforoLegendTest extends TestCase
{
    public function test_percentage_legend_renders_thresholds(): void
    {
        $html = Blade::render('<x-comges.semaforo-legend :useScoreScale="false" />');

        $this->assertStringContainsString('semaforo-legend', $html);
        $this->assertStringContainsString('100% — Cumplimiento total', $html);
        $this->assertStringContainsString('≥75% a &lt;100% — Cumplimiento parcial', $html);
        $this->assertStringContainsString('&lt;75% — Cumplimiento insuficiente', $html);
        $this->assertStringContainsString('S/I — Sin información', $html);
    }

    public function test_score_legend_renders_merged_points_and_percentage(): void
    {
        $html = Blade::render('<x-comges.semaforo-legend :useScoreScale="true" />');

        $this->assertStringContainsString('4 ptos. (100%) — Cumplimiento total', $html);
        $this->assertStringContainsString('3 a 3,99 ptos. (≥75% a &lt;100%) — Cumplimiento parcial', $html);
        $this->assertStringContainsString('&lt;3 ptos. (&lt;75%) — Cumplimiento insuficiente', $html);
        $this->assertStringContainsString('semaforo-legend', $html);
    }

    public function test_null_use_score_scale_defaults_to_percentage(): void
    {
        $html = Blade::render('<x-comges.semaforo-legend />');

        $this->assertStringContainsString('100% — Cumplimiento total', $html);
        $this->assertStringNotContainsString('ptos.', $html);
    }
}
