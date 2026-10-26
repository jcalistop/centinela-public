<?php

namespace Tests\Unit\Support\Cen;

use App\Support\Cen\CenCenterExportApiColumnMap;
use Tests\TestCase;

class CenCenterExportApiColumnMapTest extends TestCase
{
    public function test_header_order_starts_with_official_api_fields(): void
    {
        $headers = CenCenterExportApiColumnMap::headerOrder();

        $this->assertSame('EstablecimientoCodigoAntiguo', $headers[0]);
        $this->assertSame('EstablecimientoCodigo', $headers[1]);
        $this->assertContains('SeremiSaludCodigo_ServicioDeSaludCodigo', $headers);
        $this->assertContains('ComunaGlosa', $headers);
        $this->assertContains('NombreVia', $headers);
        $this->assertNotContains('cen_centers.codigo', $headers);
    }

    public function test_centinela_fields_are_appended_after_api_columns(): void
    {
        $headers = CenCenterExportApiColumnMap::headerOrder();
        $lastApiIndex = array_search('FechaCierre', $headers, true);

        $this->assertIsInt($lastApiIndex);
        $this->assertSame('Centinela_Id', $headers[$lastApiIndex + 1]);
    }
}
