<?php

namespace Tests\Unit\Services\Cen;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenAddress;
use App\Models\CenCenter;
use App\Services\Cen\CenCenterSyncAttributeDiff;
use Tests\TestCase;

class CenCenterSyncAttributeDiffTest extends TestCase
{
    public function test_detects_changed_center_attribute(): void
    {
        $center = CenCenter::withoutEvents(fn () => new CenCenter([
            'nombre_oficial' => 'Antes',
            'estado_funcionamiento' => CentroEstadoFuncionamiento::Cerrado,
        ]));

        $diff = (new CenCenterSyncAttributeDiff)->between($center, [
            'nombre_oficial' => 'Después',
            'estado_funcionamiento' => CentroEstadoFuncionamiento::VigenteEnOperacionHabitual,
            'synced_at' => now(),
        ], null);

        $this->assertArrayHasKey('nombre_oficial', $diff);
        $this->assertSame('Antes', $diff['nombre_oficial']['old']);
        $this->assertSame('Después', $diff['nombre_oficial']['new']);
        $this->assertArrayHasKey('estado_funcionamiento', $diff);
        $this->assertArrayNotHasKey('synced_at', $diff);
    }

    public function test_ignores_coordinate_changes_when_only_trailing_zeros_differ(): void
    {
        $center = CenCenter::withoutEvents(fn () => new CenCenter([
            'nombre_oficial' => 'Centro',
        ]));

        $center->setRelation('cenAddress', new CenAddress([
            'latitud' => '-41.46832970',
            'longitud' => '-72.92749720',
        ]));

        $diff = (new CenCenterSyncAttributeDiff)->between($center, [
            'nombre_oficial' => 'Centro',
            'synced_at' => now(),
        ], [
            'latitud' => '-41.4683297',
            'longitud' => '-72.9274972',
        ]);

        $this->assertArrayNotHasKey('address.latitud', $diff);
        $this->assertArrayNotHasKey('address.longitud', $diff);
    }

    public function test_detects_real_coordinate_changes(): void
    {
        $center = CenCenter::withoutEvents(fn () => new CenCenter([
            'nombre_oficial' => 'Centro',
        ]));

        $center->setRelation('cenAddress', new CenAddress([
            'latitud' => '-41.46832970',
            'longitud' => '-72.92749720',
        ]));

        $diff = (new CenCenterSyncAttributeDiff)->between($center, [
            'nombre_oficial' => 'Centro',
            'synced_at' => now(),
        ], [
            'latitud' => '-41.46833000',
            'longitud' => '-72.92749720',
        ]);

        $this->assertArrayHasKey('address.latitud', $diff);
        $this->assertSame('-41.46832970', $diff['address.latitud']['old']);
        $this->assertSame('-41.46833000', $diff['address.latitud']['new']);
        $this->assertArrayNotHasKey('address.longitud', $diff);
    }
}
