<?php

namespace Tests\Unit\Helpers;

use App\Helpers\Helper;
use Tests\TestCase;

class HelperSemaphoreTest extends TestCase
{
    public function test_muestra_semaforo_null_shows_si(): void
    {
        $html = Helper::muestra_semaforo(null, 75);

        $this->assertStringContainsString('semaforo nulo', $html);
        $this->assertStringContainsString('S/I', $html);
    }

    public function test_muestra_semaforo_100_is_green_with_percent(): void
    {
        $html = Helper::muestra_semaforo(100, 75);

        $this->assertStringContainsString('semaforo green', $html);
        $this->assertStringContainsString('100%', $html);
    }

    public function test_muestra_semaforo_sobre_100_is_green(): void
    {
        $html = Helper::muestra_semaforo(120, 75);

        $this->assertStringContainsString('semaforo green', $html);
        $this->assertStringContainsString('120%', $html);
    }

    public function test_muestra_semaforo_80_is_orange(): void
    {
        $html = Helper::muestra_semaforo(80, 75);

        $this->assertStringContainsString('semaforo orange', $html);
    }

    public function test_muestra_semaforo_50_is_red(): void
    {
        $html = Helper::muestra_semaforo(50, 75);

        $this->assertStringContainsString('semaforo red', $html);
    }

    public function test_muestra_puntaje_85_is_error_not_percentage_scale(): void
    {
        $html = Helper::muestra_puntaje(85);

        $this->assertStringContainsString('semaforo error', $html);
    }

    public function test_muestra_puntaje_4_is_green(): void
    {
        $html = Helper::muestra_puntaje(4.0);

        $this->assertStringContainsString('semaforo green', $html);
        $this->assertStringNotContainsString('%', $html);
    }
}
