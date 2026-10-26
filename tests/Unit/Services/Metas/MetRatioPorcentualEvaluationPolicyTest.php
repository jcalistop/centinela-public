<?php

namespace Tests\Unit\Services\Metas;

use App\Models\MetResult;
use App\Services\Metas\MetRatioPorcentualEvaluationPolicy;
use Tests\TestCase;

class MetRatioPorcentualEvaluationPolicyTest extends TestCase
{
    private MetRatioPorcentualEvaluationPolicy $policy;

    /** @var array<int, array<string, mixed>> */
    private array $formulaTokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(MetRatioPorcentualEvaluationPolicy::class);
        $this->formulaTokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ];
    }

    /** @test */
    public function subanual_cero_sobre_cero_devuelve_cero(): void
    {
        $result = $this->policy->evaluate(
            'MES-12',
            0.0,
            0.0,
            $this->formulaTokens,
            ['A' => 0.0, 'B' => 0.0],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(0.0, $result['result']);
    }

    /** @test */
    public function anual_cero_sobre_cero_devuelve_cero(): void
    {
        $result = $this->policy->evaluate(
            'ANUAL',
            0.0,
            0.0,
            $this->formulaTokens,
            ['A' => 0.0, 'B' => 0.0],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(0.0, $result['result']);
    }

    /** @test */
    public function subanual_cero_sobre_denominador_positivo_calcula_cero_por_ciento(): void
    {
        $result = $this->policy->evaluate(
            'MES-03',
            0.0,
            5.0,
            $this->formulaTokens,
            ['A' => 0.0, 'B' => 5.0],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(0.0, $result['result']);
    }

    /** @test */
    public function numerador_positivo_con_denominador_cero_devuelve_divisor_cero(): void
    {
        $result = $this->policy->evaluate(
            'MES-01',
            10.0,
            0.0,
            $this->formulaTokens,
            ['A' => 10.0, 'B' => 0.0],
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(MetResult::MESSAGE_DIVISOR_CERO, $result['message']);
    }

    /** @test */
    public function subanual_con_componentes_validos_calcula_porcentaje(): void
    {
        $result = $this->policy->evaluate(
            'MES-06',
            3.0,
            10.0,
            $this->formulaTokens,
            ['A' => 3.0, 'B' => 10.0],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(30.0, $result['result']);
    }
}
