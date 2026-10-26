<?php

namespace Tests\Unit\Support\Cen;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Cen\CentroPertenenciaSnss;
use App\Support\Cen\CenCatalogGlosaResolver;
use Tests\TestCase;

class CenCatalogGlosaResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CenCatalogGlosaResolver::resetCacheForTesting();
    }

    public function test_passes_through_vigente_api_glosa(): void
    {
        $resolution = CenCatalogGlosaResolver::resolve(
            'estado_funcionamiento',
            CentroEstadoFuncionamiento::class,
            'Vigente en Operación Habitual',
        );

        $this->assertSame(CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value, $resolution->persistedValue);
        $this->assertFalse($resolution->equivalenceApplied);
        $this->assertFalse($resolution->isUnknown);
    }

    public function test_collapses_cierre_definitivo_to_cerrado(): void
    {
        $resolution = CenCatalogGlosaResolver::resolve(
            'estado_funcionamiento',
            CentroEstadoFuncionamiento::class,
            'Cierre Definitivo',
        );

        $this->assertSame(CentroEstadoFuncionamiento::Cerrado->value, $resolution->persistedValue);
        $this->assertTrue($resolution->equivalenceApplied);
        $this->assertSame('collapse:Cerrado', $resolution->rule);
    }

    public function test_maps_legacy_operacion_habitual_via_syntax(): void
    {
        $resolution = CenCatalogGlosaResolver::resolve(
            'estado_funcionamiento',
            CentroEstadoFuncionamiento::class,
            'OPERACION HABITUAL',
        );

        $this->assertSame(CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value, $resolution->persistedValue);
        $this->assertFalse($resolution->equivalenceApplied);
    }

    public function test_maps_pertenencia_long_glosa_after_syntax(): void
    {
        $resolution = CenCatalogGlosaResolver::resolve(
            'pertenencia_snss',
            CentroPertenenciaSnss::class,
            'Perteneciente al Sistema Nacional de Servicios de Salud',
        );

        $this->assertSame(CentroPertenenciaSnss::Perteneciente->value, $resolution->persistedValue);
        $this->assertFalse($resolution->equivalenceApplied);
    }

    public function test_is_closed_value_helper(): void
    {
        $this->assertTrue(CentroEstadoFuncionamiento::isClosedValue('Cerrado'));
        $this->assertFalse(CentroEstadoFuncionamiento::isClosedValue('Vigente en Operación Habitual'));
    }
}
