<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Configuration;

use App\Support\Configuration\ConfigurationService;
use App\Support\Configuration\InstanceIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfigurationServiceInstanceIdentityBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_override_config_applies_instance_identity_from_database(): void
    {
        foreach (
            [
                'instance.ss_display_name' => 'SS Puente',
                'instance.ss_url' => 'https://ss-puente.gob.cl/',
                'instance.unit_name' => 'Unidad Puente',
            ] as $key => $value
        ) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'display_name' => $key,
                    'value' => $value,
                    'details' => '',
                    'type' => 'text',
                    'order' => 1,
                    'group' => 'Servicio de Salud',
                ]);
            } else {
                DB::table('settings')->where('key', $key)->update(['value' => $value]);
            }
        }

        $service = new ConfigurationService;
        $service->invalidate();
        $service->overrideConfig();

        $this->assertSame('SS Puente', InstanceIdentity::displayName());
        $this->assertSame('https://ss-puente.gob.cl/', InstanceIdentity::url());
        $this->assertSame('Unidad Puente', InstanceIdentity::unitName());

        DB::table('settings')->where('key', 'instance.ss_url')->update(['value' => '']);
        $service->invalidate();
        $service->overrideConfig();

        $this->assertSame('', InstanceIdentity::url());
    }
}
