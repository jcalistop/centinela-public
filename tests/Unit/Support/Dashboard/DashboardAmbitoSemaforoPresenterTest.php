<?php

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\DashboardAmbitoSemaforoPresenter;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DashboardAmbitoSemaforoPresenterTest extends TestCase
{
    private DashboardAmbitoSemaforoPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presenter = new DashboardAmbitoSemaforoPresenter;
    }

    public function test_value_uses_orange_for_partial_compliance(): void
    {
        $html = $this->presenter->value(80.0);

        $this->assertStringContainsString("class='semaforo orange'", $html);
        $this->assertStringContainsString('80%', $html);
    }

    public function test_value_uses_green_for_full_compliance(): void
    {
        $html = $this->presenter->value(100.0);

        $this->assertStringContainsString("class='semaforo green'", $html);
        $this->assertStringContainsString('100%', $html);
    }

    public function test_value_uses_percent_scale_for_comges_score_conversion(): void
    {
        $html = $this->presenter->value(87.5);

        $this->assertStringContainsString("class='semaforo orange'", $html);
        $this->assertStringContainsString('88%', $html);
    }

    public function test_ambito_semaforo_legend_blade_renders_unified_scale(): void
    {
        $html = Blade::render('<x-centinela.dashboard.ambito-semaforo-legend />');

        $this->assertStringContainsString('100% — Cumplimiento total', $html);
        $this->assertStringContainsString('≥75% a &lt;100% — Cumplimiento parcial', $html);
        $this->assertStringContainsString('&lt;75% — Cumplimiento insuficiente', $html);
        $this->assertStringContainsString('S/I — Sin información', $html);
        $this->assertStringNotContainsString('Leyenda COMGES', $html);
        $this->assertStringNotContainsString('Leyenda Metas', $html);
    }
}
