<?php

namespace Tests\Feature;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\View\Components\MenuComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\View;
use TCG\Voyager\Models\DataType;
use Tests\TestCase;

class MetasTwoLevelModuleEnablementTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private CenHieDependency $ss;

    private IndicatorType $metas18834;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 18834,
            'codigo' => 'RELON',
            'nombre' => 'Servicio de Salud del Reloncaví',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        // SDD-263: capa 1 exige estado=true para cualquier rol (incluido control-gestión).
        $this->metas18834 = $this->indicatorTypeByRoute('metas-18834', ['estado' => true]);

        DataType::unguarded(fn () => DataType::firstOrCreate(
            ['name' => 'indicator_types'],
            [
                'slug' => 'instrumentos-evaluacion',
                'display_name_singular' => 'Tipo de indicador',
                'display_name_plural' => 'Tipos de indicador',
                'model_name' => IndicatorType::class,
                'generate_permissions' => 0,
                'server_side' => 0,
            ]
        ));
    }

    /** @test */
    public function control_gestion_sees_menu_after_admin_enables_ss_toggle_only(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);
        $cg = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);
        $this->assertInstanceOf(User::class, $cg);
        $cg->role()->associate($cgRole)->save();

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->metas18834->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => $this->ss->id,
            'etapa_vigente' => 'CERRADO',
            'habilitado' => false,
        ]));

        $this->actingAs($cg)->withSession(['indicator_year' => $this->year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertTrue(
            $items->contains('route', 'metas-18834'),
            'Control-gestión debe ver metas-18834 tras alta admin SS aunque habilitado anual sea false.'
        );
    }

    /** @test */
    public function referente_hidden_from_menu_when_estado_false_even_if_habilitado_true(): void
    {
        // SDD-263 — capa 1 Off: no aparece en menú aunque habilitado anual sea true.
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);
        $this->assertInstanceOf(User::class, $referente);
        $referente->role()->associate($referenteRole)->save();

        IndicatorType::unguarded(fn () => $this->metas18834->update(['estado' => false]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->metas18834->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => $this->ss->id,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $this->actingAs($referente)->withSession(['indicator_year' => $this->year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $items = $view->getData()['dataTypeContent'];

        $this->assertFalse(
            $items->contains('route', 'metas-18834'),
            'Referente no debe ver metas-18834 con estado global Off (SDD-263).'
        );
    }

    /** @test */
    public function referente_http_403_when_habilitado_true_and_estado_false(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);
        $this->assertInstanceOf(User::class, $referente);
        $referente->role()->associate($referenteRole)->save();

        IndicatorType::unguarded(fn () => $this->metas18834->update(['estado' => false]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->metas18834->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => $this->ss->id,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $response = $this->actingAs($referente)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/metas-18834');

        $response->assertStatus(403);
    }

    /** @test */
    public function referente_sees_menu_after_control_gestion_enables_year(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);
        $this->assertInstanceOf(User::class, $referente);
        $referente->role()->associate($referenteRole)->save();

        IndicatorType::unguarded(fn () => $this->metas18834->update(['estado' => true]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->metas18834->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => $this->ss->id,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $this->actingAs($referente)->withSession(['indicator_year' => $this->year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $items = $view->getData()['dataTypeContent'];

        $this->assertTrue(
            $items->contains('route', 'metas-18834'),
            'Referente debe ver metas-18834 cuando habilitado anual es true.'
        );
    }

    /** @test */
    public function control_gestion_http_access_matches_menu_with_ss_toggle_only(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);
        $cg = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);
        $this->assertInstanceOf(User::class, $cg);
        $cg->role()->associate($cgRole)->save();

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        $response = $this->actingAs($cg)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/metas-18834');

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function referente_gets_403_until_year_habilitado(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);
        $this->assertInstanceOf(User::class, $referente);
        $referente->role()->associate($referenteRole)->save();

        IndicatorType::unguarded(fn () => $this->metas18834->update(['estado' => true]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        $response = $this->actingAs($referente)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/metas-18834');

        $response->assertStatus(403);
    }

    /** @test */
    public function no_role_sees_menu_when_ss_toggle_disabled(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);
        $cg = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);
        $this->assertInstanceOf(User::class, $cg);
        $cg->role()->associate($cgRole)->save();

        IndicatorType::unguarded(fn () => $this->metas18834->update(['estado' => true]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => false,
        ]));

        $this->actingAs($cg)->withSession(['indicator_year' => $this->year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $items = $view->getData()['dataTypeContent'];

        $this->assertFalse($items->contains('route', 'metas-18834'));
    }
}
