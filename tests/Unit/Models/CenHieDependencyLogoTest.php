<?php

namespace Tests\Unit\Models;

use App\Models\CenHieDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CenHieDependencyLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_logo_public_url_falls_back_to_static_asset(): void
    {
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS10',
            'nombre' => 'SS Logo Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'logo' => null,
        ]));

        $this->assertSame(asset(CenHieDependency::DEFAULT_LOGO_ASSET), $ss->logoPublicUrl());
    }

    public function test_logo_public_url_uses_public_storage_route_and_normalizes_slashes(): void
    {
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS11',
            'nombre' => 'SS Logo Path',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'logo' => 'servicios-de-salud\\August2026\\demo.jpg',
        ]));

        $ss->refresh();
        $this->assertSame('servicios-de-salud/August2026/demo.jpg', $ss->logo);
        $this->assertSame(
            route('centinela.storage.ss-logo', ['filepath' => 'August2026/demo.jpg']),
            $ss->logoPublicUrl()
        );
    }
}
