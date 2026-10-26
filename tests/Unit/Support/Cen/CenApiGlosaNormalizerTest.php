<?php

namespace Tests\Unit\Support\Cen;

use App\Support\Cen\CenApiGlosaNormalizer;
use PHPUnit\Framework\TestCase;

class CenApiGlosaNormalizerTest extends TestCase
{
    public function test_maps_legacy_estado_funcionamiento_to_api_glosa(): void
    {
        $this->assertSame(
            'Vigente en Operación Habitual',
            CenApiGlosaNormalizer::estadoFuncionamiento('OPERACION HABITUAL')
        );
    }

    public function test_passes_through_api_glosa_unchanged(): void
    {
        $this->assertSame(
            'Vigente en Operación Habitual',
            CenApiGlosaNormalizer::estadoFuncionamiento('Vigente en Operación Habitual')
        );
    }

    public function test_maps_legacy_via_to_title_case(): void
    {
        $this->assertSame('Calle', CenApiGlosaNormalizer::via('CALLE'));
    }

    public function test_maps_api_estado_cerrado(): void
    {
        $this->assertSame('Cerrado', CenApiGlosaNormalizer::estadoFuncionamiento('Cerrado'));
    }

    public function test_maps_api_pertenencia_snss_long_glosa(): void
    {
        $this->assertSame(
            'Perteneciente',
            CenApiGlosaNormalizer::pertenenciaSnss('Perteneciente al Sistema Nacional de Servicios de Salud')
        );
    }

    public function test_maps_nivel_atencion_case_insensitive(): void
    {
        $this->assertSame('Secundario', CenApiGlosaNormalizer::nivelAtencion('Secundario'));
        $this->assertSame('Secundario', CenApiGlosaNormalizer::nivelAtencion('SECUNDARIO'));
    }

    public function test_maps_tiene_urgencia_no(): void
    {
        $this->assertSame('No', CenApiGlosaNormalizer::tieneUrgencia('NO'));
    }
}
