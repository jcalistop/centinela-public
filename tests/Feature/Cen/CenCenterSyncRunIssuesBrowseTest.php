<?php

namespace Tests\Feature\Cen;

use App\Enums\Cen\CenCenterSyncRunChangeAction;
use App\Http\Controllers\Voyager\CenCenterController;
use App\Models\CenCenter;
use App\Models\CenCenterSyncRun;
use App\Models\CenCenterSyncRunChange;
use App\Models\CenCenterSyncRunIssue;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use TCG\Voyager\Models\DataType;
use Tests\TestCase;

class CenCenterSyncRunIssuesBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn (): ?bool => true);
        $this->ensureEstablecimientosDataType();
    }

    public function test_historial_partial_shows_incidencias_column_and_ver_incidencias_link(): void
    {
        $run = CenCenterSyncRun::query()->create([
            'scope' => 'full',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        CenCenterSyncRunIssue::query()->create([
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => 'EST-1',
            'severity' => 'warning',
            'code' => 'unmapped_enum',
            'message' => 'Glosa no mapeada',
        ]);

        $run->setAttribute('issues_count', 1);
        $run->setAttribute('pending_unmapped_issues_count', 1);

        $html = $this->renderSyncHistoryPartial(
            syncRuns: new LengthAwarePaginator([$run], 1, 20),
        );

        $this->assertStringContainsString('Incidencias', $html);
        $this->assertStringContainsString('Ver detalle', $html);
        $this->assertStringContainsString('sync_run='.$run->id, $html);
        $this->assertStringContainsString('run_panel=changes', $html);
        $this->assertStringContainsString('change_action=created', $html);
        $this->assertStringNotContainsString('Warn.', $html);
    }

    public function test_sync_run_changes_detail_lists_created_establishments(): void
    {
        $run = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '24',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
            'created_count' => 1,
        ]);

        CenCenterSyncRunChange::query()->create([
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => 'CH-99',
            'nombre_display' => 'Centro Chaitén',
            'action' => 'created',
        ]);

        $run->setAttribute('issues_count', 0);

        $html = $this->renderSyncHistoryPartial(
            selectedSyncRun: $run,
            syncRunChanges: new LengthAwarePaginator(
                CenCenterSyncRunChange::query()->where('cen_center_sync_run_id', $run->id)->get(),
                1,
                25,
            ),
            syncRunPanel: 'changes',
            activeChangeAction: CenCenterSyncRunChangeAction::Created,
        );

        $this->assertStringContainsString('Detalle de la ejecución #'.$run->id, $html);
        $this->assertStringContainsString('CH-99', $html);
        $this->assertStringContainsString('Centro Chaitén', $html);
        $this->assertStringContainsString('Creados', $html);
    }

    public function test_sync_run_issues_partial_lists_persisted_rows(): void
    {
        $run = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '24',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        CenCenterSyncRunIssue::query()->create([
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => 'CH-99',
            'severity' => 'skipped',
            'code' => 'manual_origen',
            'message' => 'manual_origen',
        ]);

        $issues = new LengthAwarePaginator(
            CenCenterSyncRunIssue::query()->where('cen_center_sync_run_id', $run->id)->get(),
            1,
            25,
        );

        $run->setAttribute('issues_count', 1);

        $html = $this->renderSyncHistoryPartial(
            selectedSyncRun: $run,
            syncRunIssues: $issues,
            syncRunPanel: 'issues',
        );

        $this->assertStringContainsString('Detalle de la ejecución #'.$run->id, $html);
        $this->assertStringContainsString('CH-99', $html);
        $this->assertStringContainsString('Registro manual', $html);
        $this->assertStringContainsString('Volver al historial', $html);
    }

    public function test_detail_renders_attribute_diff_in_updated_tab(): void
    {
        $run = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '24',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
            'updated_count' => 1,
        ]);

        CenCenterSyncRunChange::query()->create([
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => 'EST-55',
            'nombre_display' => 'Hospital Sur',
            'action' => 'updated',
            'detail' => 'estado_funcionamiento',
            'context' => [
                'changes' => [
                    'estado_funcionamiento' => [
                        'old' => 'Cerrado',
                        'new' => 'Vigente en Operación Habitual',
                    ],
                ],
            ],
        ]);

        $html = $this->renderSyncHistoryPartial(
            selectedSyncRun: $run,
            syncRunChanges: new LengthAwarePaginator(
                CenCenterSyncRunChange::query()->where('cen_center_sync_run_id', $run->id)->get(),
                1,
                25,
            ),
            syncRunPanel: 'changes',
            activeChangeAction: CenCenterSyncRunChangeAction::Updated,
        );

        $this->assertStringContainsString('Estado de funcionamiento', $html);
        $this->assertStringContainsString('Cerrado', $html);
        $this->assertStringContainsString('Vigente en Operación Habitual', $html);
    }

    public function test_detail_shows_run_updated_count_when_change_rows_missing(): void
    {
        $run = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '24',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
            'updated_count' => 12,
            'created_count' => 0,
        ]);

        $html = $this->renderSyncHistoryPartial(
            selectedSyncRun: $run,
            syncRunChanges: new LengthAwarePaginator([], 0, 25),
            syncRunPanel: 'changes',
            activeChangeAction: CenCenterSyncRunChangeAction::Updated,
        );

        $this->assertStringContainsString('badge">12</span>', $html);
        $this->assertStringContainsString('Totales sin listado por establecimiento', $html);
    }

    public function test_historial_ver_detalle_opens_first_active_change_tab(): void
    {
        $run = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '24',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
            'updated_count' => 3,
            'created_count' => 0,
        ]);

        $html = $this->renderSyncHistoryPartial(
            syncRuns: new LengthAwarePaginator([$run], 1, 20),
        );

        $this->assertStringContainsString('change_action=updated', $html);
    }

    public function test_find_sync_run_for_browse_denies_other_ss_to_control_gestion(): void
    {
        $ssOwn = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'SS Reloncaví',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $runOther = CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '15',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ssOwn->id,
        ]);
        $user->role()->associate($role)->save();

        $this->actingAs($user);

        $request = Request::create('/admin/establecimientos', 'GET', [
            'tab' => 'historial',
            'sync_run' => (string) $runOther->id,
        ]);
        $route = new Route('GET', 'admin/establecimientos', []);
        $route->name('voyager.establecimientos.index');
        $request->setRouteResolver(fn (): Route => $route);

        Config::set('centinela.national_establishments.enabled', true);

        $response = app(CenCenterController::class)->index($request);
        $viewData = $response->getData();
        $this->assertNull($viewData['selectedSyncRun'] ?? null);
        $this->assertNull($viewData['syncRunIssues'] ?? null);
    }

    /**
     * @param  LengthAwarePaginator<CenCenterSyncRun>|null  $syncRuns
     * @param  LengthAwarePaginator<CenCenterSyncRunIssue>|null  $syncRunIssues
     * @param  LengthAwarePaginator<CenCenterSyncRunChange>|null  $syncRunChanges
     */
    private function renderSyncHistoryPartial(
        ?LengthAwarePaginator $syncRuns = null,
        ?CenCenterSyncRun $selectedSyncRun = null,
        ?LengthAwarePaginator $syncRunIssues = null,
        ?LengthAwarePaginator $syncRunChanges = null,
        string $syncRunPanel = 'changes',
        ?CenCenterSyncRunChangeAction $activeChangeAction = null,
    ): string {
        return view('voyager::establecimientos.partials.browse-tab-sync-history', [
            'syncRuns' => $syncRuns,
            'selectedSyncRun' => $selectedSyncRun,
            'syncRunIssues' => $syncRunIssues,
            'syncRunChanges' => $syncRunChanges,
            'syncRunPanel' => $syncRunPanel,
            'activeChangeAction' => $activeChangeAction ?? CenCenterSyncRunChangeAction::Created,
            'canManageGlosaEquivalences' => false,
        ])->render();
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
