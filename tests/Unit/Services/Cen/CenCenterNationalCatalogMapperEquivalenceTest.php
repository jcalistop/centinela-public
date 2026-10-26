<?php

namespace Tests\Unit\Services\Cen;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenHieDependency;
use App\Services\Cen\CenCenterNationalCatalogMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CenCenterNationalCatalogMapperEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_cierre_definitivo_to_cerrado_with_equiv_warning(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '15',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
        ]));

        $mapped = app(CenCenterNationalCatalogMapper::class)->map([
            'EstablecimientoCodigo' => '100099',
            'EstablecimientoGlosa' => 'CESFAM Cerrado',
            'SeremiSaludCodigo_ServicioDeSaludCodigo' => '15',
            'TipoEstablecimientoGlosa' => 'Centro de Salud Familiar (CESFAM)',
            'AmbitoFuncionamiento' => 'Establecimiento de Salud',
            'Certificacion' => 'No Aplica',
            'DependenciaAdministrativa' => 'Servicio de Salud',
            'TipoPertenenciaEstabGlosa' => 'Perteneciente',
            'NivelAtencionEstabglosa' => 'Primario',
            'TieneServicioUrgencia' => 'No',
            'TipoUrgencia' => 'No Aplica',
            'ClasificacionTipoSapu' => 'No Aplica',
            'TipoSistemaSaludGlosa' => 'Público',
            'EstadoFuncionamiento' => 'Cierre Definitivo',
            'NivelComplejidadEstabGlosa' => 'Baja',
            'TipoAtencionEstabGlosa' => 'Abierta',
        ]);

        $this->assertSame(CentroEstadoFuncionamiento::Cerrado->value, $mapped->centerAttributes['estado_funcionamiento']);

        $codes = array_column($mapped->warnings, 'code');
        $this->assertContains('glosa_equiv_applied', $codes);
    }
}
