<?php

namespace Tests\Unit\Support\Metas;

use App\Enums\Periodicidad;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use App\Support\Metas\MetIndicatorMiniFichaPresenter;
use Tests\TestCase;

class MetIndicatorMiniFichaPresenterTest extends TestCase
{
    /** @test */
    public function trunca_objetivo_largo_y_expone_formula(): void
    {
        $indicator = new MetIndicator([
            'unidad_medida' => '%',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_operador' => '>=',
            'objetivo' => str_repeat('A', 250),
        ]);

        $formula = new MetFormula(['description' => 'A / B * 100']);
        $indicator->setRelation('formula', $formula);

        $periodicityResolver = $this->createMock(MetIndicatorPeriodicityResolver::class);
        $periodicityResolver->method('resolveForCarga')
            ->willReturn(Periodicidad::Semestral);

        $presenter = new MetIndicatorMiniFichaPresenter($periodicityResolver);

        $ficha = $presenter->forDataModal($indicator, 1, 1, 20.0, 18.5, true);

        $this->assertSame('SEMESTRAL', $ficha['periodicidad']);
        $this->assertSame('%', $ficha['unidad_medida']);
        $this->assertTrue($ficha['comprometido']);
        $this->assertSame(20.0, $ficha['meta_nacional']);
        $this->assertSame(18.5, $ficha['meta_efectiva']);
        $this->assertSame('A / B * 100', $ficha['formula_expression']);
        $this->assertStringEndsWith('…', $ficha['objetivo']);
        $this->assertLessThanOrEqual(220, mb_strlen($ficha['objetivo']));
        $this->assertStringNotContainsString('<', $ficha['objetivo']);
    }

    /** @test */
    public function excerpt_de_objetivo_html_es_plain_sin_tags(): void
    {
        $indicator = new MetIndicator([
            'unidad_medida' => '%',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_operador' => '>=',
            'objetivo' => '<p><strong>'.str_repeat('B', 300).'</strong></p>',
        ]);

        $indicator->setRelation('formula', new MetFormula(['description' => 'A / B']));

        $periodicityResolver = $this->createMock(MetIndicatorPeriodicityResolver::class);
        $periodicityResolver->method('resolveForCarga')
            ->willReturn(Periodicidad::Semestral);

        $presenter = new MetIndicatorMiniFichaPresenter($periodicityResolver);
        $ficha = $presenter->forDataModal($indicator, 1, 1, 10.0, 10.0);

        $this->assertNotNull($ficha['objetivo']);
        $this->assertStringNotContainsString('<', $ficha['objetivo']);
        $this->assertStringNotContainsString('strong', $ficha['objetivo']);
        $this->assertLessThanOrEqual(220, mb_strlen($ficha['objetivo']));
        $this->assertStringEndsWith('…', $ficha['objetivo']);
    }
}
