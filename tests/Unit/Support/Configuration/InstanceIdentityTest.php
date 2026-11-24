<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Configuration;

use App\Support\Configuration\InstanceIdentity;
use Tests\TestCase;

class InstanceIdentityTest extends TestCase
{
    public function test_display_name_falls_back_to_ss_de_prueba(): void
    {
        config(['centinela.instance.ss_display_name' => '']);

        $this->assertSame('SS de prueba', InstanceIdentity::displayName());
    }

    public function test_display_name_uses_config_value(): void
    {
        config(['centinela.instance.ss_display_name' => 'SS Arauco']);

        $this->assertSame('SS Arauco', InstanceIdentity::displayName());
    }

    public function test_url_empty_when_blank_or_invalid(): void
    {
        config(['centinela.instance.ss_url' => '']);
        $this->assertSame('', InstanceIdentity::url());

        config(['centinela.instance.ss_url' => 'not-a-url']);
        $this->assertSame('', InstanceIdentity::url());
    }

    public function test_url_returns_valid_https(): void
    {
        config(['centinela.instance.ss_url' => 'https://ejemplo.gob.cl/']);

        $this->assertSame('https://ejemplo.gob.cl/', InstanceIdentity::url());
    }

    public function test_unit_name_fallback(): void
    {
        config(['centinela.instance.unit_name' => '  ']);

        $this->assertSame(
            'Departamento de Control de Gestión',
            InstanceIdentity::unitName()
        );
    }
}
