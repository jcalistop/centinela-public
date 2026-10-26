<?php

namespace Tests\Unit\Support\Configuration;

use App\Support\Configuration\ConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfigurationServiceMetasRecalcSettingsBridgeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function override_config_applies_metas_recalc_settings_from_database(): void
    {
        DB::table('settings')
            ->where('key', 'module.metas_recalc_scope_enabled')
            ->update(['value' => 'Off']);
        DB::table('settings')
            ->where('key', 'system.metas_recalc_debounce_seconds')
            ->update(['value' => '120']);

        $service = new ConfigurationService;
        $service->overrideConfig();

        $this->assertFalse((bool) config('centinela.metas.recalculation_scope_enabled'));
        $this->assertSame('120', config('centinela.metas.recalculation_debounce_seconds'));
    }
}
