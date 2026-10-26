<?php

namespace Tests\Unit\Models;

use App\Models\CenHieDependency;
use App\Models\ServiceModuleToggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CenHieDependencyEnabledModuleTogglesTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_service_module_toggles_only_returns_enabled_rows_for_that_ss(): void
    {
        $ssA = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS-A',
            'nombre' => 'Servicio A',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $ssB = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS-B',
            'nombre' => 'Servicio B',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $ssA->id,
            'module_key' => 'comges',
            'enabled' => true,
        ]);
        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $ssA->id,
            'module_key' => 'adp',
            'enabled' => false,
        ]);
        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $ssB->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]);

        $enabledA = $ssA->enabledServiceModuleToggles()->pluck('module_key')->all();
        $enabledB = $ssB->enabledServiceModuleToggles()->pluck('module_key')->all();

        $this->assertSame(['comges'], $enabledA);
        $this->assertSame(['metas-18834'], $enabledB);
    }
}
