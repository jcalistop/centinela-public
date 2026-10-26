<?php

namespace Tests\Unit\Support\Metas;

use App\Support\Metas\MetSemaforoThresholds;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MetSemaforoThresholdsTest extends TestCase
{
    #[DataProvider('ley18834Provider')]
    public function test_ley_18834_thresholds(float $value, ?string $tier, string $labelClass): void
    {
        $scheme = MetSemaforoThresholds::fromIndicatorRoute('metas-18834');

        $this->assertSame($tier, $scheme->tier($value));
        $this->assertSame($labelClass, $scheme->labelClass($value));
    }

    /**
     * @return array<string, array{0: float, 1: ?string, 2: string}>
     */
    public static function ley18834Provider(): array
    {
        return [
            'verde 100' => [100.0, 'green', 'success'],
            'amarillo 99' => [99.0, 'yellow', 'warning'],
            'amarillo 75' => [75.0, 'yellow', 'warning'],
            'rojo 74' => [74.9, 'red', 'danger'],
        ];
    }

    #[DataProvider('ley19664Provider')]
    public function test_ley_19664_and_20707_thresholds(float $value, ?string $tier, string $labelClass): void
    {
        foreach (['metas-19664', 'metas-20707'] as $route) {
            $scheme = MetSemaforoThresholds::fromIndicatorRoute($route);

            $this->assertSame($tier, $scheme->tier($value), $route);
            $this->assertSame($labelClass, $scheme->labelClass($value), $route);
        }
    }

    /**
     * @return array<string, array{0: float, 1: ?string, 2: string}>
     */
    public static function ley19664Provider(): array
    {
        return [
            'verde 95' => [95.0, 'green', 'success'],
            'verde 100' => [100.0, 'green', 'success'],
            'amarillo 94' => [94.9, 'yellow', 'warning'],
            'amarillo 75' => [75.0, 'yellow', 'warning'],
            'rojo 74' => [74.9, 'red', 'danger'],
        ];
    }

    public function test_legend_labels_differ_by_route(): void
    {
        $s18834 = MetSemaforoThresholds::fromIndicatorRoute('metas-18834');
        $s19664 = MetSemaforoThresholds::fromIndicatorRoute('metas-19664');

        $this->assertSame('100%', $s18834->greenLegend);
        $this->assertSame('≥95%', $s19664->greenLegend);
    }

    #[DataProvider('indicatorUmbralComplianceProvider')]
    public function test_indicator_umbral_compliance_labels(
        float $cumplimiento,
        ?float $umbralSuperior,
        ?float $umbralInferior,
        string $labelClass,
    ): void {
        $this->assertSame(
            $labelClass,
            MetSemaforoThresholds::labelClassForIndicatorCompliance(
                $cumplimiento,
                $umbralSuperior,
                $umbralInferior,
            ),
        );
    }

    /**
     * @return array<string, array{0: float, 1: ?float, 2: ?float, 3: string}>
     */
    public static function indicatorUmbralComplianceProvider(): array
    {
        return [
            'verde tope 100' => [100.0, 100.0, null, 'success'],
            'verde al alcanzar umbral superior 95' => [95.0, 95.0, null, 'success'],
            'verde muy sobre el umbral (defensivo)' => [120.0, 100.0, 50.0, 'success'],
            'amarillo en 99' => [99.0, 100.0, null, 'warning'],
            'amarillo en 75' => [75.0, 100.0, null, 'warning'],
            'amarillo sobre umbral inferior' => [80.0, 95.0, 60.0, 'warning'],
            'amarillo justo en el umbral inferior (aporta proporcional)' => [60.0, 95.0, 60.0, 'warning'],
            'amarillo bajo 75 con aporte proporcional (sin piso global)' => [74.9, 100.0, 50.0, 'warning'],
            'amarillo en 60 con umbrales por defecto' => [60.0, 100.0, 0.0, 'warning'],
            'rojo bajo umbral inferior' => [90.0, 99.5, 95.0, 'danger'],
            'rojo cumplimiento cero' => [0.0, 99.5, null, 'danger'],
            'rojo cumplimiento cero con umbral inferior cero' => [0.0, 100.0, 0.0, 'danger'],
            'umbral null usa 100 como verde' => [100.0, null, null, 'success'],
        ];
    }

    public function test_metas_semaforo_legend_blade_renders_global_18834(): void
    {
        $html = Blade::render(
            '<x-metas.semaforo-legend context="global" route="metas-18834" />',
        );

        $this->assertStringContainsString('100% — Cumplimiento global satisfactorio', $html);
        $this->assertStringContainsString('75% a 99% — Cumplimiento parcial', $html);
    }

    public function test_metas_semaforo_legend_blade_renders_indicator_umbrales(): void
    {
        $html = Blade::render(
            '<x-metas.semaforo-legend context="indicator" :umbral-superior="95" :umbral-inferior="60" />',
        );

        $this->assertStringContainsString('≥95% — Cumplimiento satisfactorio', $html);
        $this->assertStringContainsString('60% a &lt;95% — Cumplimiento parcial', $html);
        $this->assertStringContainsString('&lt;60% — Cumplimiento insuficiente', $html);
    }

    public function test_metas_semaforo_legend_indicator_sin_umbral_inferior_no_usa_piso_global(): void
    {
        $html = Blade::render(
            '<x-metas.semaforo-legend context="indicator" :umbral-superior="100" :umbral-inferior="0" />',
        );

        $this->assertStringContainsString('100% — Cumplimiento satisfactorio', $html);
        $this->assertStringContainsString('&lt;100% — Cumplimiento parcial', $html);
        $this->assertStringContainsString('0% — Cumplimiento insuficiente', $html);
        $this->assertStringNotContainsString('75%', $html);
    }

    public function test_metas_semaforo_legend_blade_renders_mixed_con_nota(): void
    {
        $html = Blade::render('<x-metas.semaforo-legend context="mixed" />');

        $this->assertStringContainsString('Cumplimiento satisfactorio', $html);
        $this->assertStringContainsString('Cumplimiento parcial', $html);
        $this->assertStringContainsString('Cumplimiento insuficiente', $html);
        $this->assertStringNotContainsString('Aporte proporcional', $html);
        $this->assertStringNotContainsString('Aporta el 100', $html);
        $this->assertStringContainsString('<span class="semaforo-legend__note text-muted pull-right">', $html);
        $this->assertStringContainsString(
            'El color refleja el cumplimiento respecto de los umbrales de cada indicador (ver ficha). Solo los indicadores en convenio aportan al cumplimiento global.',
            $html,
        );
    }

    public function test_resolve_legend_global_por_ruta(): void
    {
        $legend = MetSemaforoThresholds::resolveLegend('global', route: 'metas-19664');

        $this->assertSame('≥95%', $legend['green']['range']);
        $this->assertSame('Cumplimiento global satisfactorio', $legend['green']['label']);
    }

    public function test_global_legend_footnote(): void
    {
        $footnote = MetSemaforoThresholds::fromIndicatorRoute('metas-18834')->globalLegendFootnote();

        $this->assertSame(
            'Cumplimiento global: 100% verde, 75% a 99% amarillo, <75% rojo.',
            $footnote,
        );
    }

    public function test_border_color_for_indicator_compliance(): void
    {
        $this->assertSame(
            '#f0ad4e',
            MetSemaforoThresholds::borderColorForIndicatorCompliance(80.0, 95.0),
        );
        $this->assertSame(
            '#5cb85c',
            MetSemaforoThresholds::borderColorForIndicatorCompliance(100.0, 95.0),
        );
    }

    public function test_global_green_umbral_por_ruta(): void
    {
        $this->assertSame(100.0, MetSemaforoThresholds::fromIndicatorRoute('metas-18834')->globalGreenUmbral());
        $this->assertSame(95.0, MetSemaforoThresholds::fromIndicatorRoute('metas-19664')->globalGreenUmbral());
    }

    public function test_defaults_for_route_equivale_a_from_indicator_route(): void
    {
        foreach (['metas-18834', 'metas-19664', 'metas-20707'] as $route) {
            $fromRoute = MetSemaforoThresholds::fromIndicatorRoute($route);
            $defaults = MetSemaforoThresholds::defaultsForRoute($route);

            $this->assertSame($fromRoute->greenMinPercent, $defaults->greenMinPercent, $route);
            $this->assertSame($fromRoute->yellowMinPercent, $defaults->yellowMinPercent, $route);
        }
    }

    public function test_resolve_legend_global_usa_ambos_umbrales(): void
    {
        $legend = MetSemaforoThresholds::resolveLegend(
            'global',
            90.0,
            null,
            null,
            70.0,
        );

        $this->assertSame('≥90%', $legend['green']['range']);
        $this->assertSame('70% a 89%', $legend['yellow']['range']);
        $this->assertSame('<70%', $legend['red']['range']);
        $this->assertSame('Cumplimiento global satisfactorio', $legend['green']['label']);
    }

    public function test_metas_semaforo_legend_blade_global_con_ambos_umbrales(): void
    {
        $html = Blade::render(
            '<x-metas.semaforo-legend context="global" :umbral-superior="90" :umbral-amarillo="70" />',
        );

        $this->assertStringContainsString('≥90% — Cumplimiento global satisfactorio', $html);
        $this->assertStringContainsString('70% a 89% — Cumplimiento parcial', $html);
        $this->assertStringContainsString('&lt;70% — Cumplimiento insuficiente', $html);
    }

    public function test_ruta_desconocida_usa_95_75_y_registra_advertencia(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'ruta de instrumento desconocida')
                    && ($context['route'] ?? null) === 'metas-desconocida';
            });

        $scheme = MetSemaforoThresholds::defaultsForRoute('metas-desconocida');

        $this->assertSame(95.0, $scheme->greenMinPercent);
        $this->assertSame(75.0, $scheme->yellowMinPercent);
        $this->assertSame('success', $scheme->labelClass(95.0));
        $this->assertSame('warning', $scheme->labelClass(80.0));
        $this->assertSame('danger', $scheme->labelClass(72.0));
    }
}
