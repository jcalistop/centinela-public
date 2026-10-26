<?php

namespace Tests\Unit\Enums\Metas;

use App\Enums\Metas\DataFieldInputType;
use App\Enums\Metas\MetInstrumentPeriodo;
use PHPUnit\Framework\TestCase;

class MetInstrumentPeriodoTest extends TestCase
{
    /** @test */
    public function from_month_and_input_type_resolves_mensual_trimestral_semestral_y_anual(): void
    {
        $this->assertSame(MetInstrumentPeriodo::Mes02, MetInstrumentPeriodo::fromMonthAndInputType(2, DataFieldInputType::Mensual));
        $this->assertSame(MetInstrumentPeriodo::Trim2, MetInstrumentPeriodo::fromMonthAndInputType(6, DataFieldInputType::Trimestral));
        $this->assertSame(MetInstrumentPeriodo::Sem2, MetInstrumentPeriodo::fromMonthAndInputType(12, DataFieldInputType::Semestral));
        $this->assertSame(MetInstrumentPeriodo::Anual, MetInstrumentPeriodo::fromMonthAndInputType(12, DataFieldInputType::Anual));
        $this->assertNull(MetInstrumentPeriodo::fromMonthAndInputType(2, DataFieldInputType::Trimestral));
    }
}
