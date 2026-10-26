<?php

namespace Tests\Feature\Cen;

use App\Http\Controllers\Voyager\CenCenterController;
use App\Models\CenAddress;
use App\Models\CenCenter;
use App\Models\CenCommune;
use App\Models\CenHieDependency;
use App\Models\CenProvince;
use App\Models\CenRegion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\SimpleExcel\SimpleExcelReader;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class CenCenterExcelExportTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn (): ?bool => true);

        $this->ensureEstablecimientosDataType();
    }

    public function test_control_gestion_can_download_establishments_xlsx_with_database_columns(): void
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
        $this->grantBrowseAdminPermissionToRole($role);

        $center = $this->createMinimalCenCenter([
            'codigo' => 'EXP-001',
            'cen_hie_dependency_id' => $ss->id,
            'nombre_corto' => 'Centro Exportable',
        ]);

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

        CenAddress::unguarded(fn () => CenAddress::create([
            'cen_center_id' => $center->id,
            'cen_commune_id' => $commune->id,
            'via' => 'Calle',
            'numero' => '100',
            'direccion' => 'Principal 100',
            'latitud' => -42.915,
            'longitud' => -72.706,
        ]));

        $otherSs = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '15',
            'nombre' => 'Servicio de Salud Arica',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $this->createMinimalCenCenter([
            'codigo' => 'EXP-OTRO-SS',
            'cen_hie_dependency_id' => $otherSs->id,
        ]);

        $request = Request::create(route('voyager.establecimientos.export'), 'GET');
        $route = app('router')->getRoutes()->getByName('voyager.establecimientos.export');
        $request->setRouteResolver(fn () => $route);

        $this->actingAs($user);
        $response = app(CenCenterController::class)->exportExcel($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition') ?? '');

        $tempPath = $response->getFile()->getPathname();

        $headers = SimpleExcelReader::create($tempPath)->getHeaders();
        $rows = SimpleExcelReader::create($tempPath)->getRows()->toArray();

        @unlink($tempPath);

        $this->assertContains('EstablecimientoCodigo', $headers);
        $this->assertContains('Latitud', $headers);
        $this->assertContains('ComunaGlosa', $headers);
        $this->assertContains('Centinela_NombreCorto', $headers);
        $this->assertNotContains('cen_centers.codigo', $headers);

        $this->assertCount(1, $rows);
        $this->assertSame('EXP-001', $rows[0]['EstablecimientoCodigo']);
        $this->assertSame('Centro Exportable', $rows[0]['Centinela_NombreCorto']);
        $this->assertSame('Chaitén', $rows[0]['ComunaGlosa']);
    }

    private function grantBrowseAdminPermissionToRole(Role $role): void
    {
        $id = Permission::firstOrCreate(
            ['key' => 'browse_admin'],
            ['table_name' => 'admin']
        )->id;
        $role->permissions()->syncWithoutDetaching([$id]);
    }

    private function ensureEstablecimientosDataType(): void
    {
        DataType::unguarded(function (): void {
            if (DataType::query()->where('slug', 'establecimientos')->exists()) {
                return;
            }

            DataType::create([
                'name' => 'establecimientos',
                'slug' => 'establecimientos',
                'display_name_singular' => 'Establecimiento',
                'display_name_plural' => 'Establecimientos',
                'icon' => 'voyager-shop',
                'model_name' => CenCenter::class,
                'controller' => CenCenterController::class,
                'generate_permissions' => 0,
                'server_side' => 0,
            ]);
        });
    }
}
