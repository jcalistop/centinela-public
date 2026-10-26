<?php

namespace Tests\Unit\Support\Cen;

use App\Models\CenHieDependency;
use App\Support\Cen\CenServicioSaludCodigo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CenServicioSaludCodigoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_candidates_include_unpadded_and_padded_variants(): void
    {
        $this->assertSame(['05', '5'], CenServicioSaludCodigo::lookupCandidates('05'));
        $this->assertSame(['5', '05'], CenServicioSaludCodigo::lookupCandidates('5'));
        $this->assertSame(['15'], CenServicioSaludCodigo::lookupCandidates('15'));
        $this->assertSame(['24'], CenServicioSaludCodigo::lookupCandidates('24'));
    }

    public function test_to_api_filter_codigo_zero_pads_single_digit_numeric_codes(): void
    {
        $this->assertSame('05', CenServicioSaludCodigo::toApiFilterCodigo('5'));
        $this->assertSame('09', CenServicioSaludCodigo::toApiFilterCodigo('09'));
        $this->assertSame('24', CenServicioSaludCodigo::toApiFilterCodigo('24'));
        $this->assertSame('95', CenServicioSaludCodigo::toApiFilterCodigo('95'));
    }

    public function test_find_dependency_resolves_padded_catalog_codigo_against_unpadded_maestro(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '5',
            'nombre' => 'SEREMI de Valparaíso',
            'tipo_dependencia' => 'SEREMI',
        ]));

        $dependency = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '5',
            'nombre' => 'Servicio de Salud Coquimbo',
            'tipo_dependencia' => 'SERVICIO',
        ]));

        $resolved = CenServicioSaludCodigo::findDependency('05');

        $this->assertNotNull($resolved);
        $this->assertSame($dependency->id, $resolved->id);
        $this->assertSame('SERVICIO', $resolved->tipo_dependencia);
    }
}
