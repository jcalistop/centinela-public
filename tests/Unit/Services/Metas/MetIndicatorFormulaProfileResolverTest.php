<?php

namespace Tests\Unit\Services\Metas;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\MetIndicatorFormulaProfile;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Services\Metas\MetIndicatorFormulaProfileResolver;
use Tests\TestCase;

class MetIndicatorFormulaProfileResolverTest extends TestCase
{
    private MetIndicatorFormulaProfileResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(MetIndicatorFormulaProfileResolver::class);
    }

    /** @test */
    public function detecta_ratio_porcentual_con_un_numerador_y_un_denominador(): void
    {
        $indicator = $this->makeIndicatorWithFormula([
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ]);

        $this->assertSame(
            MetIndicatorFormulaProfile::RatioPorcentual11,
            $this->resolver->resolve($indicator),
        );
    }

    /** @test */
    public function detecta_ratio_porcentual_con_parentesis(): void
    {
        $indicator = $this->makeIndicatorWithFormula([
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ]);

        $this->assertSame(
            MetIndicatorFormulaProfile::RatioPorcentual11,
            $this->resolver->resolve($indicator),
        );
    }

    /** @test */
    public function detecta_ratio_porcentual_con_orden_de_claves_como_en_bd(): void
    {
        $indicator = $this->makeIndicatorWithFormula([
            ['type' => 'paren', 'value' => '('],
            ['key' => 'A', 'type' => 'var'],
            ['type' => 'op', 'value' => '/'],
            ['key' => 'B', 'type' => 'var'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ]);

        $this->assertSame(
            MetIndicatorFormulaProfile::RatioPorcentual11,
            $this->resolver->resolve($indicator),
        );
    }

    /** @test */
    public function retorna_generico_si_hay_mas_de_un_numerador(): void
    {
        $indicator = $this->makeIndicatorWithFormula([
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ], numeradorCount: 2);

        $this->assertSame(
            MetIndicatorFormulaProfile::Generico,
            $this->resolver->resolve($indicator),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $expression
     */
    private function makeIndicatorWithFormula(array $expression, int $numeradorCount = 1): MetIndicator
    {
        $fields = collect();

        for ($i = 0; $i < $numeradorCount; $i++) {
            $fields->push(new MetDataField([
                'variable_key' => $i === 0 ? 'A' : 'C',
                'componente' => DataFieldComponente::Numerador,
            ]));
        }

        $fields->push(new MetDataField([
            'variable_key' => 'B',
            'componente' => DataFieldComponente::Denominador,
        ]));

        $indicator = new MetIndicator([
            'decimales' => 2,
            'decimales_modo' => 'redondear',
        ]);

        $indicator->setRelation('dataFields', $fields);
        $indicator->setRelation('formula', new MetFormula([
            'expression' => $expression,
        ]));

        return $indicator;
    }
}
