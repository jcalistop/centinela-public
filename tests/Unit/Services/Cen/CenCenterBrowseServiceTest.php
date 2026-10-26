<?php

namespace Tests\Unit\Services\Cen;

use App\Enums\Cen\DireccionVia;
use App\Models\CenAddress;
use App\Models\CenCommune;
use App\Models\CenHieDependency;
use App\Models\CenProvince;
use App\Models\CenRegion;
use App\Models\Role;
use App\Models\User;
use App\Services\Cen\CenCenterBrowseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class CenCenterBrowseServiceTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Session::forget(CenCenterBrowseService::SESSION_KEY);
    }

    public function test_builds_markers_and_list_for_georeferenced_centers_in_user_ss(): void
    {
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'Servicio de Salud Del Reloncaví',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $user->role()->associate($role)->save();

        $region = CenRegion::unguarded(fn () => CenRegion::create([
            'codigo' => '10',
            'nombre' => 'Los Lagos',
        ]));
        $province = CenProvince::unguarded(fn () => CenProvince::create([
            'codigo' => '104',
            'nombre' => 'Chiloé',
            'cen_region_id' => $region->id,
        ]));
        $commune = CenCommune::unguarded(fn () => CenCommune::create([
            'codigo' => '10401',
            'nombre' => 'Chaitén',
            'cen_province_id' => $province->id,
        ]));

        $withCoords = $this->createMinimalCenCenter([
            'codigo' => 'GEO-1',
            'cen_hie_dependency_id' => $ss->id,
            'nombre_corto' => 'Centro Con Mapa',
        ]);

        $via = DireccionVia::Calle->value;

        CenAddress::unguarded(fn () => CenAddress::create([
            'cen_center_id' => $withCoords->id,
            'cen_commune_id' => $commune->id,
            'via' => $via,
            'latitud' => '-42.915',
            'longitud' => '-72.716',
        ]));

        $this->createMinimalCenCenter([
            'codigo' => 'NO-GEO',
            'cen_hie_dependency_id' => $ss->id,
        ]);

        $context = app(CenCenterBrowseService::class)->buildForUser($user);

        $this->assertSame('24', $context['ssCodigo']);
        $this->assertSame(2, $context['totalCount']);
        $this->assertSame(1, $context['georeferencedCount']);
        $this->assertSame(1, $context['withoutCoordinatesCount']);
        $this->assertCount(1, $context['markers']);
        $this->assertSame('GEO-1', $context['markers'][0]['codigo']);
        $this->assertSame(-42.915, $context['markers'][0]['lat']);
        $this->assertCount(2, $context['listItems']);
        $noGeoItem = collect($context['listItems'])->firstWhere('codigo', 'NO-GEO');
        $this->assertNotNull($noGeoItem);
        $this->assertFalse($noGeoItem['hasCoordinates']);
        $geoItem = collect($context['listItems'])->firstWhere('codigo', 'GEO-1');
        $this->assertNotNull($geoItem);
        $this->assertTrue($geoItem['hasCoordinates']);
        $this->assertNull($geoItem['madreDescripcion']);
        $this->assertSame('Centro de Salud', $geoItem['tipo']);
        $this->assertNotEmpty($context['listColumns']);
        $this->assertSame(15, $context['listPerPage']);
        $this->assertNotEmpty($context['filterOptions']['establishments']);
        $this->assertCount(2, $context['filterOptions']['establishments']);
        $this->assertFalse($context['exploringForeignSs']);
        $this->assertSame((int) $ss->id, $context['effectiveSsId']);
    }

    public function test_browse_switches_to_requested_ss_and_marks_exploring(): void
    {
        $ssA = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'SS A',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $ssB = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '25',
            'nombre' => 'SS B',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ssA->id,
        ]);
        $user->role()->associate($role)->save();

        $this->createMinimalCenCenter([
            'codigo' => 'A-1',
            'cen_hie_dependency_id' => $ssA->id,
        ]);
        $this->createMinimalCenCenter([
            'codigo' => 'B-1',
            'cen_hie_dependency_id' => $ssB->id,
            'nombre_corto' => 'Centro B',
        ]);

        $service = app(CenCenterBrowseService::class);
        $service->applyRequestedSs($user, $ssB->id);
        $context = $service->buildForUser($user);

        $this->assertTrue($context['exploringForeignSs']);
        $this->assertSame((int) $ssB->id, $context['effectiveSsId']);
        $this->assertSame('25', $context['ssCodigo']);
        $this->assertSame(1, $context['totalCount']);
        $this->assertSame('B-1', $context['listItems'][0]['codigo']);
        $this->assertSame((int) $ssA->id, $user->fresh()->cen_hie_dependency_id);
    }

    public function test_invalid_ss_falls_back_to_user_ss(): void
    {
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'SS A',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $user->role()->associate($role)->save();

        $service = app(CenCenterBrowseService::class);
        $effective = $service->applyRequestedSs($user, 999999);

        $this->assertSame((int) $ss->id, $effective);
        $this->assertFalse($service->isExploringForeignSs($user));
    }

    public function test_user_without_ss_has_empty_browse_until_pick(): void
    {
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'SS A',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $user->role()->associate($adminRole)->save();
        $user->cen_hie_dependency_id = null;

        $this->createMinimalCenCenter([
            'codigo' => 'A-1',
            'cen_hie_dependency_id' => $ss->id,
        ]);

        $service = app(CenCenterBrowseService::class);
        $empty = $service->buildForUser($user);
        $this->assertNull($empty['effectiveSsId']);
        $this->assertSame(0, $empty['totalCount']);

        $service->applyRequestedSs($user, $ss->id);
        $picked = $service->buildForUser($user);
        $this->assertSame((int) $ss->id, $picked['effectiveSsId']);
        $this->assertSame(1, $picked['totalCount']);
        $this->assertFalse($picked['exploringForeignSs']);
    }
}
