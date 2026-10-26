<?php

namespace Tests\Unit\Support\Metas;

use App\Support\Metas\MetMonthlyDataInputFormatter;
use PHPUnit\Framework\TestCase;

class MetMonthlyDataInputFormatterTest extends TestCase
{
    /** @test */
    public function format_for_input_omite_decimales_en_enteros(): void
    {
        $this->assertSame('100', MetMonthlyDataInputFormatter::formatForInput('100.0000'));
        $this->assertSame('0', MetMonthlyDataInputFormatter::formatForInput(0));
    }

    /** @test */
    public function format_for_input_conserva_decimales_significativos(): void
    {
        $this->assertSame('12.34', MetMonthlyDataInputFormatter::formatForInput('12.3400'));
        $this->assertSame('12.5', MetMonthlyDataInputFormatter::formatForInput(12.5));
    }

    /** @test */
    public function format_for_input_retorna_null_para_valor_vacio(): void
    {
        $this->assertNull(MetMonthlyDataInputFormatter::formatForInput(null));
        $this->assertNull(MetMonthlyDataInputFormatter::formatForInput(''));
    }
}
