<?php

declare(strict_types=1);

namespace Tests\Feature\Configuracion;

use App\Providers\SettingsBridgeServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VoyagerCompassEnabledSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_is_seeded_in_admin_group_as_toggle_off_by_default(): void
    {
        $row = DB::table('settings')
            ->where('key', 'admin.voyager_compass_enabled')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Admin', $row->group);
        $this->assertSame('radio_btn', $row->type);
        $this->assertSame('Off', $row->value);
        $this->assertSame('Habilitar Compass de Voyager', $row->display_name);
    }

    public function test_settings_bridge_overrides_voyager_compass_in_production(): void
    {
        config(['voyager.compass_in_production' => false]);

        DB::table('settings')
            ->where('key', 'admin.voyager_compass_enabled')
            ->update(['value' => 'On']);

        (new SettingsBridgeServiceProvider($this->app))->boot();

        $this->assertTrue((bool) config('voyager.compass_in_production'));
    }
}
