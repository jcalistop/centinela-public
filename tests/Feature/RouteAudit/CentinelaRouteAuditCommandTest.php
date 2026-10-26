<?php

namespace Tests\Feature\RouteAudit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CentinelaRouteAuditCommandTest extends TestCase
{
    /** @test */
    public function route_audit_command_succeeds_with_strict_high_gaps(): void
    {
        $exit = Artisan::call('centinela:route-audit', [
            '--fail-on-high-gaps' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
    }

    /** @test */
    public function route_audit_writes_inventory_json_when_requested(): void
    {
        $path = storage_path('framework/testing-route-inventory.json');

        $exit = Artisan::call('centinela:route-audit', [
            '--inventory-out' => $path,
        ]);

        $this->assertSame(0, $exit);
        $this->assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
        File::delete($path);
    }
}
