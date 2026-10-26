<?php

namespace Tests\Unit;

use App\Models\MetResult;
use PHPUnit\Framework\TestCase;

class MetResultStoredMessageTest extends TestCase
{
    /** @test */
    public function nd_es_ausencia_de_datos_para_la_ui(): void
    {
        $this->assertTrue(MetResult::storedErrorMessageIndicatesMissingData('N/D'));
        $this->assertTrue(MetResult::storedErrorMessageIndicatesNoCasesInPeriod(MetResult::MESSAGE_SIN_CASOS));
        $this->assertTrue(MetResult::storedErrorMessageIsNonCalculationAlert(MetResult::MESSAGE_SIN_CASOS));
        $this->assertFalse(MetResult::storedErrorMessageIndicatesMissingData(MetResult::MESSAGE_SIN_CASOS));
    }

    /** @test */
    public function otros_mensajes_no_se_consideran_solo_ausencia_de_datos(): void
    {
        $this->assertFalse(MetResult::storedErrorMessageIndicatesMissingData('Fórmula inválida'));
        $this->assertFalse(MetResult::storedErrorMessageIndicatesMissingData('Error: divisor cero'));
        $this->assertFalse(MetResult::storedErrorMessageIndicatesMissingData(null));
        $this->assertFalse(MetResult::storedErrorMessageIndicatesMissingData(''));
    }
}
