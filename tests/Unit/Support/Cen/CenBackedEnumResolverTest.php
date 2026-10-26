<?php

namespace Tests\Unit\Support\Cen;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Support\Cen\CenBackedEnumResolver;
use Tests\TestCase;

class CenBackedEnumResolverTest extends TestCase
{
    public function test_resolves_known_api_glosa(): void
    {
        $resolved = CenBackedEnumResolver::resolve(CentroEstadoFuncionamiento::class, 'Cerrado');

        $this->assertSame(CentroEstadoFuncionamiento::Cerrado, $resolved);
    }

    public function test_returns_null_for_unmapped_glosa_instead_of_desconocido_fallback(): void
    {
        $this->assertNull(
            CenBackedEnumResolver::resolve(CentroEstadoFuncionamiento::class, 'Glosa inventada')
        );
    }
}
