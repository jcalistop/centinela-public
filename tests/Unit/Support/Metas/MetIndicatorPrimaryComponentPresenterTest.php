<?php

namespace Tests\Unit\Support\Metas;

use App\Enums\Metas\DataFieldComponente;
use App\Models\MetDataField;
use App\Support\Metas\MetIndicatorPrimaryComponentPresenter;
use Tests\TestCase;

class MetIndicatorPrimaryComponentPresenterTest extends TestCase
{
    /** @test */
    public function detecta_formula_compleja_cuando_hay_multiples_componentes_primarios(): void
    {
        $fields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'A'),
            $this->makeField(DataFieldComponente::Numerador, 'C'),
            $this->makeField(DataFieldComponente::Denominador, 'B'),
            $this->makeField(DataFieldComponente::Denominador, 'D'),
        ]);

        $this->assertTrue(MetIndicatorPrimaryComponentPresenter::usesComplexPrimaryComponents($fields));
    }

    /** @test */
    public function no_detecta_formula_compleja_con_un_solo_numerador_y_denominador(): void
    {
        $fields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'A'),
            $this->makeField(DataFieldComponente::Denominador, 'B'),
        ]);

        $this->assertFalse(MetIndicatorPrimaryComponentPresenter::usesComplexPrimaryComponents($fields));
    }

    private function makeField(DataFieldComponente $componente, string $variableKey): MetDataField
    {
        return new MetDataField([
            'variable_key' => $variableKey,
            'componente' => $componente,
        ]);
    }
}
