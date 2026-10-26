<?php

namespace Tests\Unit\Services\Metas;

use App\Services\Metas\MetFormulaValidationService;
use Tests\TestCase;

class MetFormulaValidationServiceTest extends TestCase
{
    private MetFormulaValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MetFormulaValidationService::class);
    }

    /** @test */
    public function acepta_formula_resta_en_denominador_met_18834_2025_12(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'op', 'value' => '-'],
            ['type' => 'var', 'key' => 'C'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ];

        $result = $this->service->validate($tokens, ['A', 'B', 'C']);

        $this->assertTrue($result['ok']);
    }

    /** @test */
    public function rechaza_division_literal_por_cero(): void
    {
        $tokens = [
            ['type' => 'lit', 'value' => '1'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'lit', 'value' => '0'],
        ];

        $result = $this->service->validate($tokens);

        $this->assertFalse($result['ok']);
        $this->assertSame(MetFormulaValidationService::PHASE_DIVISION, $result['phase']);
        $this->assertSame(
            'La fórmula contiene una división por cero inválida.',
            $result['message'],
        );
    }

    /** @test */
    public function rechaza_denominador_tautologico_b_menos_b(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'op', 'value' => '-'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'paren', 'value' => ')'],
        ];

        $result = $this->service->validate($tokens, ['A', 'B']);

        $this->assertFalse($result['ok']);
        $this->assertSame(MetFormulaValidationService::PHASE_DIVISION, $result['phase']);
        $this->assertSame(
            'La fórmula contiene una división por cero inválida.',
            $result['message'],
        );
    }

    /** @test */
    public function rechaza_denominador_cero_por_producto(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'paren', 'value' => '('],
            ['type' => 'lit', 'value' => '0'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'paren', 'value' => ')'],
        ];

        $result = $this->service->validate($tokens, ['A']);

        $this->assertFalse($result['ok']);
        $this->assertSame(MetFormulaValidationService::PHASE_DIVISION, $result['phase']);
    }

    /** @test */
    public function acepta_division_contextual_a_sobre_b(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
        ];

        $result = $this->service->validate($tokens, ['A', 'B']);

        $this->assertTrue($result['ok']);
    }

    /** @test */
    public function rechaza_variable_sin_campo(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'Z'],
        ];

        $result = $this->service->validate($tokens, ['A', 'B']);

        $this->assertFalse($result['ok']);
        $this->assertSame(MetFormulaValidationService::PHASE_REFERENCES, $result['phase']);
        $this->assertStringContainsString('Z', $result['message']);
    }

    /** @test */
    public function rechaza_parentesis_desbalanceados(): void
    {
        $tokens = [
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'paren', 'value' => ')'],
        ];

        $result = $this->service->validate($tokens);

        $this->assertFalse($result['ok']);
        $this->assertSame(MetFormulaValidationService::PHASE_STRUCTURE, $result['phase']);
    }

    /** @test */
    public function rechaza_min_con_argumento_vacio(): void
    {
        $tokens = [
            [
                'type' => 'fn',
                'name' => 'min',
                'args' => [
                    [],
                    [['type' => 'lit', 'value' => '1']],
                ],
            ],
        ];

        $result = $this->service->validate($tokens);

        $this->assertFalse($result['ok']);
        $this->assertSame(
            'El argumento 1 de min() no puede estar vacío.',
            $result['message'],
        );
    }
}
