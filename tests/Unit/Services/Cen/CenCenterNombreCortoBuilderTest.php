<?php

namespace Tests\Unit\Services\Cen;

use App\Services\Cen\CenCenterNombreCortoBuilder;
use PHPUnit\Framework\TestCase;

class CenCenterNombreCortoBuilderTest extends TestCase
{
    public function test_abbreviates_cesfam_in_glosa(): void
    {
        $builder = new CenCenterNombreCortoBuilder;

        $result = $builder->fromEstablecimientoGlosa('Centro de Salud Familiar Pudahuel');

        $this->assertStringContainsString('CESFAM', $result);
    }

    public function test_direccion_ss_prefix_produces_short_name(): void
    {
        $builder = new CenCenterNombreCortoBuilder;

        $glosa = 'Actividades gestionadas por la Dirección del Servicio para apoyo de la Red (S.S Reloncaví)';

        $result = $builder->fromEstablecimientoGlosa($glosa);

        $this->assertSame('Dirección SS Reloncaví', $result);
    }
}
