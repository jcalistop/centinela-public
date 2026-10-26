<?php

namespace Tests\Feature;

use App\Helpers\ModuleAccess;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MenuVisualization;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Support\Voyager\MenuVisualizationTree;
use App\Support\Voyager\YearDropdownCatalog;
use App\View\Components\DropdownFromModelComponent;
use App\View\Components\MenuComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class VoyagerShellPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private CenHieDependency $ss;

    private Year $year;

    private User $cgUser;

    protected function setUp(): void
    {
        parent::setUp();

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS-SHELL',
            'nombre' => 'Servicio Shell Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->cgUser = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        // SDD-263: ModuleAccess exige indicator_types.estado=true para instrumentos.
        foreach (
            [
                'adp' => 'adp',
                'metas-18834' => 'metas-18834',
                'metas-19664' => 'metas-19664',
                'metas-20707' => 'metas-20707',
                'voyager.compromisos.index' => 'comges',
            ] as $route => $clave
        ) {
            $type = IndicatorType::query()->where('route', $route)->first();
            if ($type === null) {
                IndicatorType::unguarded(fn () => IndicatorType::create([
                    'clave_indicador' => $clave,
                    'nombre_indicador' => $clave,
                    'descripcion_indicador' => 'Fixture SDD-268 / shell performance',
                    'icon_class' => 'voyager-helm',
                    'route' => $route,
                    'view' => null,
                    'estado' => true,
                    'orden' => 1,
                    'measurement_granularity' => 'establishment',
                ]));
            } else {
                IndicatorType::unguarded(fn () => $type->update(['estado' => true]));
            }
        }

        session(['indicator_year' => $this->year]);
        YearDropdownCatalog::invalidate();
        Cache::forget(MenuVisualizationTree::cacheKey($this->ss->id, $this->year->id));
    }

    public function test_module_access_batch_avoids_n1_on_toggles_and_ss(): void
    {
        $this->actingAs($this->cgUser);

        foreach (['adp', 'metas-18834', 'metas-19664', 'metas-20707', 'comges'] as $key) {
            ServiceModuleToggle::create([
                'cen_hie_dependency_id' => $this->ss->id,
                'module_key' => $key,
                'enabled' => true,
            ]);
        }

        ModuleAccess::flushRequestMemo();

        $toggleQueries = 0;
        $ssQueries = 0;
        DB::listen(function ($query) use (&$toggleQueries, &$ssQueries): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'service_module_toggles')) {
                $toggleQueries++;
            }
            if (str_contains($sql, 'cen_hie_dependencies')) {
                $ssQueries++;
            }
        });

        foreach (['adp', 'metas-18834', 'metas-19664', 'metas-20707', 'voyager.compromisos.index'] as $module) {
            $this->assertTrue(ModuleAccess::canAccess($module), "canAccess({$module}) debe ser true");
        }

        $this->assertSame(1, $toggleQueries, 'Debe cargar toggles del SS una sola vez.');
        $this->assertSame(1, $ssQueries, 'Debe resolver el SS una sola vez.');
    }

    public function test_menu_component_filter_reuses_indicator_type_without_extra_route_lookup(): void
    {
        DataType::unguarded(fn () => DataType::create([
            'name' => 'indicator_types',
            'slug' => 'instrumentos-evaluacion',
            'display_name_singular' => 'Tipo de indicador',
            'display_name_plural' => 'Tipos de indicador',
            'model_name' => IndicatorType::class,
            'generate_permissions' => 0,
            'server_side' => 0,
        ]));

        $this->actingAs($this->cgUser);

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]);

        ModuleAccess::flushRequestMemo();

        $routeLookups = 0;
        DB::listen(function ($query) use (&$routeLookups): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'indicator_types') && str_contains($sql, 'route')) {
                $routeLookups++;
            }
        });

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);

        $items = $view->getData()['dataTypeContent'];
        $this->assertTrue($items->contains(fn (IndicatorType $t) => $t->route === 'metas-18834'));

        $this->assertSame(
            0,
            $routeLookups,
            'El filtro del menú no debe reconsultar IndicatorType por route (modelo ya cargado).'
        );
    }

    public function test_year_dropdown_catalog_is_cached_and_invalidated(): void
    {
        YearDropdownCatalog::invalidate();

        $first = YearDropdownCatalog::all();
        $this->assertTrue($first->contains('id', $this->year->id));

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'years')) {
                $queries++;
            }
        });

        $second = YearDropdownCatalog::all();
        $this->assertSame(0, $queries);
        $this->assertCount($first->count(), $second);

        Year::unguarded(fn () => Year::create(['ano' => 2027, 'default' => 0]));
        YearDropdownCatalog::invalidate();

        $after = YearDropdownCatalog::all();
        $this->assertTrue($after->contains('ano', 2027));
    }

    public function test_dropdown_component_uses_year_catalog(): void
    {
        YearDropdownCatalog::invalidate();
        YearDropdownCatalog::all();

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (
                str_contains(strtolower($query->sql), 'from "years"')
                || str_contains(strtolower($query->sql), 'from `years`')
            ) {
                $queries++;
            }
        });

        $view = (new DropdownFromModelComponent(Year::class, 'ano', 'setIndicatorYear'))->render();
        $this->assertInstanceOf(View::class, $view);
        $this->assertSame(0, $queries);
    }

    public function test_menu_visualization_tree_is_cached_and_invalidated_on_save(): void
    {
        $this->actingAs($this->cgUser);

        MenuVisualization::unguarded(fn () => MenuVisualization::create([
            'nombre_menu' => 'Padre shell',
            'titulo' => 'Padre shell',
            'menu_superior' => null,
            'estado' => true,
            'orden' => 1,
            'cen_hie_dependency_id' => $this->ss->id,
            'year_id' => $this->year->id,
            'icon_class' => 'voyager-dashboard',
            'route' => null,
        ]));

        Cache::forget(MenuVisualizationTree::cacheKey($this->ss->id, $this->year->id));

        $first = MenuVisualizationTree::adminMenuItems();
        $this->assertNotEmpty($first);
        $this->assertSame('Padre shell', $first[0]['title']);

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'menu_visualizations')) {
                $queries++;
            }
        });

        $second = MenuVisualizationTree::adminMenuItems();
        $this->assertSame(0, $queries);
        $this->assertSame($first, $second);

        MenuVisualization::unguarded(fn () => MenuVisualization::create([
            'nombre_menu' => 'Otro padre',
            'titulo' => 'Otro padre',
            'menu_superior' => null,
            'estado' => true,
            'orden' => 2,
            'cen_hie_dependency_id' => $this->ss->id,
            'year_id' => $this->year->id,
            'icon_class' => 'voyager-dashboard',
            'route' => null,
        ]));

        $after = MenuVisualizationTree::adminMenuItems();
        $this->assertCount(2, $after);
    }
}
