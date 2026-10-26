<?php

namespace Tests\Feature\MetasInstrument;

use App\Services\MetFormulaEvaluatorService;
use Tests\TestCase;

class FormulaEvaluatorTest extends TestCase
{
    private MetFormulaEvaluatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MetFormulaEvaluatorService::class);
    }

    /** @test */
    public function evalua_formula_basica_correctamente(): void
    {
        $tokens = [
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 25, 'B' => 100], 2, 'redondear');

        $this->assertTrue($result['ok']);
        $this->assertSame(25.0, $result['result']);
    }

    /** @test */
    public function retorna_error_en_division_por_cero(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 25, 'B' => 0], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['result']);
        $this->assertSame('Error: divisor cero', $result['message']);
    }

    /** @test */
    public function retorna_cero_en_division_cero_sobre_cero(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 0, 'B' => 0], 2, 'redondear');

        $this->assertTrue($result['ok']);
        $this->assertSame(0.0, $result['result']);
    }

    /** @test */
    public function retorna_cero_en_formula_porcentual_cero_sobre_cero(): void
    {
        $tokens = [
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 0, 'B' => 0], 2, 'redondear');

        $this->assertTrue($result['ok']);
        $this->assertSame(0.0, $result['result']);
    }

    /** @test */
    public function retorna_nd_si_faltan_variables(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '+'],
            ['type' => 'var', 'key' => 'B'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 10], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertSame('N/D', $result['message']);
    }

    /** @test */
    public function retorna_formula_invalida_si_pila_desequilibrada(): void
    {
        // Expresión "(A / B) 100" ��� falta el operador * entre ) y 100
        $tokens = [
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'lit', 'value' => '100'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 50, 'B' => 100], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertSame('Fórmula inválida', $result['message']);
    }

    /** @test */
    public function retorna_formula_invalida_si_parentesis_sin_cerrar(): void
    {
        $tokens = [
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '+'],
            ['type' => 'var', 'key' => 'B'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 10, 'B' => 5], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertSame('Fórmula inválida', $result['message']);
    }

    /** @test */
    public function retorna_formula_invalida_si_operador_no_reconocido(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '%'],
            ['type' => 'var', 'key' => 'B'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 10, 'B' => 3], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertSame('Fórmula inválida', $result['message']);
    }

    /** @test */
    public function evalua_min_entre_variable_y_literal(): void
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

        $result = $this->service->evaluate($tokens, ['A' => 5], 2, 'redondear');

        $this->assertTrue($result['ok']);
        $this->assertSame(1.0, $result['result']);
    }

    /** @test */
    public function evalua_min_cuando_variable_es_menor_que_literal(): void
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

        $result = $this->service->evaluate($tokens, ['A' => 0.5], 2, 'redondear');

        $this->assertTrue($result['ok']);
        $this->assertSame(0.5, $result['result']);
    }

    /** @test */
    public function evalua_min_con_subexpresion_anidada(): void
    {
        $tokens = [
            [
                'type' => 'fn',
                'name' => 'min',
                'args' => [
                    [
                        ['type' => 'paren', 'value' => '('],
                        ['type' => 'var', 'key' => 'A'],
                        ['type' => 'op', 'value' => '/'],
                        ['type' => 'var', 'key' => 'B'],
                        ['type' => 'paren', 'value' => ')'],
                    ],
                    [['type' => 'lit', 'value' => '1']],
                ],
            ],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 150, 'B' => 100], 2, 'redondear');

        $this->assertTrue($result['ok']);
        $this->assertSame(1.0, $result['result']);
    }

    /** @test */
    public function evalua_min_combinado_con_operadores_externos(): void
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
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 5], 2, 'redondear');

        $this->assertTrue($result['ok']);
        $this->assertSame(100.0, $result['result']);
    }

    /** @test */
    public function retorna_formula_invalida_si_min_no_tiene_dos_argumentos(): void
    {
        $tokens = [
            [
                'type' => 'fn',
                'name' => 'min',
                'args' => [
                    [['type' => 'var', 'key' => 'A']],
                ],
            ],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 5], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertSame('Fórmula inválida', $result['message']);
    }

    /** @test */
    public function retorna_formula_invalida_si_funcion_no_es_min(): void
    {
        $tokens = [
            [
                'type' => 'fn',
                'name' => 'max',
                'args' => [
                    [['type' => 'var', 'key' => 'A']],
                    [['type' => 'lit', 'value' => '1']],
                ],
            ],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 5], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertSame('Fórmula inválida', $result['message']);
    }

    /** @test */
    public function retorna_nd_si_min_referencia_variable_faltante(): void
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

        $result = $this->service->evaluate($tokens, [], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertSame('N/D', $result['message']);
    }

    /** @test */
    public function retorna_error_si_min_contiene_division_por_cero(): void
    {
        $tokens = [
            [
                'type' => 'fn',
                'name' => 'min',
                'args' => [
                    [
                        ['type' => 'var', 'key' => 'A'],
                        ['type' => 'op', 'value' => '/'],
                        ['type' => 'var', 'key' => 'B'],
                    ],
                    [['type' => 'lit', 'value' => '1']],
                ],
            ],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 10, 'B' => 0], 2, 'redondear');

        $this->assertFalse($result['ok']);
        $this->assertSame('Error: divisor cero', $result['message']);
    }

    /** @test */
    public function respeta_modo_truncar(): void
    {
        $tokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
        ];

        $result = $this->service->evaluate($tokens, ['A' => 10, 'B' => 3], 2, 'truncar');

        $this->assertTrue($result['ok']);
        $this->assertSame(3.33, $result['result']);
    }
}
