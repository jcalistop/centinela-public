<?php

namespace Tests\Feature\Cen;

use App\Enums\Cen\CenCenterType;
use App\Http\Controllers\Voyager\CenCenterController;
use App\Models\CenCenter;
use App\Models\CenCenterSyncRun;
use App\Models\CenCenterSyncRunIssue;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Cen\CenCatalogGlosaEquivalenceExportService;
use App\Services\Cen\CenCatalogGlosaEquivalenceService;
use App\Support\Cen\CenCatalogGlosaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Spatie\SimpleExcel\SimpleExcelReader;
use Symfony\Component\HttpKernel\Exception\HttpException;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class CenCatalogGlosaEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn (): ?bool => true);
        CenCatalogGlosaResolver::resetCacheForTesting();
        $this->ensureEstablecimientosDataType();
    }

    public function test_sync_cache_refresh_allows_new_database_equivalence_to_resolve(): void
    {
        $fakeGlosa = 'Glosa Cache Stale Simulada';

        CenCatalogGlosaResolver::resolve('cen_type', CenCenterType::class, $fakeGlosa);
        $this->assertTrue(
            CenCatalogGlosaResolver::resolve('cen_type', CenCenterType::class, $fakeGlosa)->isUnknown,
        );

        $this->equivalenceService()->register(
            'cen_type',
            $fakeGlosa,
            CenCenterType::Case29->value,
            User::factory()->create(),
        );

        CenCatalogGlosaResolver::resetEquivalenceCache();

        $resolution = CenCatalogGlosaResolver::resolve('cen_type', CenCenterType::class, $fakeGlosa);

        $this->assertSame(CenCenterType::Case29->value, $resolution->persistedValue);
        $this->assertTrue($resolution->equivalenceApplied);
        $this->assertFalse($resolution->isUnknown);
    }

    public function test_database_equivalence_overrides_unmapped_glosa(): void
    {
        $fakeGlosa = 'Tipo Establecimiento Inventado Para Test';

        $this->equivalenceService()->register(
            'cen_type',
            $fakeGlosa,
            CenCenterType::Case29->value,
            User::factory()->create(),
        );

        $resolution = CenCatalogGlosaResolver::resolve(
            'cen_type',
            CenCenterType::class,
            $fakeGlosa,
        );

        $this->assertSame(CenCenterType::Case29->value, $resolution->persistedValue);
        $this->assertTrue($resolution->equivalenceApplied);
        $this->assertFalse($resolution->isUnknown);
    }

    public function test_admin_can_promote_unmapped_issue_to_equivalence(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => true]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '15',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
        ]));

        $run = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '15',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $issue = CenCenterSyncRunIssue::query()->create([
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => 'EST-PROMO-1',
            'severity' => 'warning',
            'code' => 'unmapped_enum',
            'message' => 'Glosa no mapeada para cen_type: Hospital Alternativo',
            'context' => [
                'field' => 'cen_type',
                'api_glosa' => 'Hospital Alternativo',
                'normalized_api' => 'Hospital Alternativo',
                'establecimiento_codigo' => 'EST-PROMO-1',
                'establecimiento_nombre' => 'Hospital Promo Test',
            ],
        ]);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->grantBrowseAdminPermissionToRole($role);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->role()->associate($role)->save();

        $response = $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->post(
                route('voyager.establecimientos.sync-issues.promote-glosa-equivalence', $issue),
                ['target_value' => CenCenterType::Case29->value],
            );

        $response->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('message');

        $this->assertDatabaseHas('cen_catalog_glosa_equivalences', [
            'field_key' => 'cen_type',
            'source_glosa' => 'Hospital Alternativo',
            'target_value' => CenCenterType::Case29->value,
            'active' => true,
        ]);

        $resolution = CenCatalogGlosaResolver::resolve(
            'cen_type',
            CenCenterType::class,
            'Hospital Alternativo',
        );

        $this->assertSame(CenCenterType::Case29->value, $resolution->persistedValue);

        $issue->refresh();
        $this->assertNotNull($issue->promoted_at);
        $this->assertSame(0, $this->equivalenceService()->paginatePromotableUnmappedIssues(50)->total());
    }

    public function test_promotable_issues_include_establishment_name_for_display(): void
    {
        $run = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '15',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        CenCenterSyncRunIssue::query()->create([
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => 'EST-LIST-1',
            'severity' => 'warning',
            'code' => 'unmapped_enum',
            'message' => 'Glosa no mapeada',
            'context' => [
                'field' => 'cen_type',
                'api_glosa' => 'Tipo Inventado',
                'establecimiento_nombre' => 'Centro Desde Contexto',
            ],
        ]);

        $paginator = $this->equivalenceService()->paginatePromotableUnmappedIssues(50);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(1, $paginator->total());

        $listed = $paginator->items()[0] ?? null;
        $this->assertInstanceOf(CenCenterSyncRunIssue::class, $listed);
        $this->assertSame('EST-LIST-1', $listed->establecimiento_codigo);
        $this->assertSame('Centro Desde Contexto', $listed->establecimiento_nombre_display);
    }

    public function test_manual_register_marks_matching_unmapped_issues_promoted(): void
    {
        $run = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '15',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $issue = CenCenterSyncRunIssue::query()->create([
            'cen_center_sync_run_id' => $run->id,
            'severity' => 'warning',
            'code' => 'unmapped_enum',
            'message' => 'Glosa no mapeada',
            'context' => [
                'field' => 'cen_type',
                'api_glosa' => 'Clinica Ambulatoria Test',
            ],
        ]);

        $user = User::factory()->create();

        $this->equivalenceService()->register(
            'cen_type',
            'Clinica Ambulatoria Test',
            CenCenterType::Case16->value,
            $user,
        );

        $issue->refresh();

        $this->assertNotNull($issue->promoted_at);
        $this->assertSame(0, $this->equivalenceService()->paginatePromotableUnmappedIssues(50)->total());
    }

    public function test_deactivate_equivalence_stops_resolution(): void
    {
        $service = $this->equivalenceService();
        $user = User::factory()->create();

        $equiv = $service->register(
            'cen_type',
            'CESFAM Variante Test',
            CenCenterType::Case16->value,
            $user,
        );

        $service->deactivate($equiv);

        $resolution = CenCatalogGlosaResolver::resolve(
            'cen_type',
            CenCenterType::class,
            'CESFAM Variante Test',
        );

        $this->assertTrue($resolution->isUnknown);
        $this->assertSame(
            CenCatalogGlosaResolver::desconocidoValue(CenCenterType::class),
            $resolution->persistedValue,
        );
    }

    public function test_admin_can_download_active_equivalences_xlsx(): void
    {
        $reviewer = User::factory()->create(['name' => 'Admin Revisor']);

        $this->equivalenceService()->register(
            'cen_type',
            'Hospital Alternativo Export',
            CenCenterType::Case29->value,
            $reviewer,
        );

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->grantBrowseAdminPermissionToRole($role);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->role()->associate($role)->save();

        $this->actingAs($user);
        $response = app(CenCenterController::class)->exportGlosaEquivalences(
            app(CenCatalogGlosaEquivalenceExportService::class),
        );

        $this->assertTrue($response->isSuccessful());
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition') ?? '');

        $tempPath = $response->getFile()->getPathname();

        $rows = SimpleExcelReader::create($tempPath)->getRows()->toArray();
        @unlink($tempPath);

        $this->assertCount(1, $rows);
        $this->assertSame('Tipo de establecimiento', $rows[0]['Campo']);
        $this->assertSame('cen_type', $rows[0]['Clave campo']);
        $this->assertSame('Hospital Alternativo Export', $rows[0]['Texto catálogo nacional']);
        $this->assertSame(CenCenterType::Case29->value, $rows[0]['Valor Centinela']);
        $this->assertSame('Admin Revisor', $rows[0]['Revisado por']);
    }

    public function test_non_admin_cannot_download_active_equivalences_xlsx(): void
    {
        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->role()->associate($role)->save();

        $this->actingAs($user);

        try {
            app(CenCenterController::class)->exportGlosaEquivalences(
                app(CenCatalogGlosaEquivalenceExportService::class),
            );
            $this->fail('Se esperaba HTTP 403 para usuarios sin rol admin.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function equivalenceService(): CenCatalogGlosaEquivalenceService
    {
        return $this->app->make(CenCatalogGlosaEquivalenceService::class);
    }

    private function grantBrowseAdminPermissionToRole(Role $role): void
    {
        $id = Permission::firstOrCreate(
            ['key' => 'browse_admin'],
            ['table_name' => 'admin'],
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
