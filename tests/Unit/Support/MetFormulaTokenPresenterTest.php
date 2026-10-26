<?php

namespace Tests\Unit\Support;

use App\Support\Metas\MetFormulaTokenPresenter;
use Tests\TestCase;

class MetFormulaTokenPresenterTest extends TestCase
{
    /** @test */
    public function validate_rechaza_parentesis_desbalanceados(): void
    {
        $tokens = [
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'paren', 'value' => ')'],
        ];

        $this->assertSame(
            'La fórmula tiene paréntesis desbalanceados.',
            MetFormulaTokenPresenter::validate($tokens),
        );
    }

    /** @test */
    public function validate_acepta_parentesis_balanceados(): void
    {
        $tokens = [
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'paren', 'value' => ')'],
        ];

        $this->assertNull(MetFormulaTokenPresenter::validate($tokens));
    }

    /** @test */
    public function validate_rechaza_argumento_vacio_en_min(): void
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

        $this->assertSame(
            'El argumento 1 de min() no puede estar vacío.',
            MetFormulaTokenPresenter::validate($tokens),
        );
    }

    /** @test */
    public function validate_rechaza_argumento_invalido_en_min(): void
    {
        $tokens = [
            [
                'type' => 'fn',
                'name' => 'min',
                'args' => [
                    [['type' => 'var', 'key' => 'A']],
                    [
                        ['type' => 'op', 'value' => '+'],
                        ['type' => 'lit', 'value' => '1'],
                    ],
                ],
            ],
        ];

        $this->assertSame(
            'La fórmula no es válida.',
            MetFormulaTokenPresenter::validate($tokens),
        );
    }

    /** @test */
    public function validate_acepta_min_con_argumentos_evaluables(): void
    {
        $tokens = [
            [
                'type' => 'fn',
                'name' => 'min',
                'args' => [
                    [['type' => 'var', 'key' => 'A']],
                    [['type' => 'lit', 'value' => '1']],
                ],
            ],
        ];

        $this->assertNull(MetFormulaTokenPresenter::validate($tokens));
    }

    /** @test */
    public function describe_renderiza_min_con_subexpresiones(): void
    {
        $tokens = [
            [
                'type' => 'fn',
                'name' => 'min',
                'args' => [
                    [['type' => 'var', 'key' => 'A']],
                    [['type' => 'lit', 'value' => '1']],
                ],
            ],
        ];

        $this->assertSame('min(A, 1)', MetFormulaTokenPresenter::describe($tokens));
    }

    /** @test */
    public function validate_acepta_formula_con_resta_en_denominador_met_18834_2025_12(): void
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

        $this->assertNull(MetFormulaTokenPresenter::validate($tokens));
    }

    /** @test */
    public function validate_rechaza_division_por_cero_algebraico_siempre_cero(): void
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

        $this->assertSame(
            'La fórmula contiene una división por cero inválida.',
            MetFormulaTokenPresenter::validate($tokens),
        );
    }

    /** @test */
    public function validate_rechaza_variable_sin_campo_cuando_se_pasan_claves(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'Z'],
        ];

        $this->assertSame(
            'La fórmula referencia variables no definidas: Z.',
            MetFormulaTokenPresenter::validate($tokens, ['A', 'B']),
        );
    }

    /** @test */
    public function validate_acepta_sin_fase_referencias_si_no_hay_claves(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'Z'],
        ];

        $this->assertNull(MetFormulaTokenPresenter::validate($tokens));
    }

    /** @test */
    public function undefined_variable_keys_detecta_referencias_ausentes_en_campos(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'Z'],
        ];

        $this->assertSame(['Z'], MetFormulaTokenPresenter::undefinedVariableKeys($tokens, ['A', 'B']));
    }
}
