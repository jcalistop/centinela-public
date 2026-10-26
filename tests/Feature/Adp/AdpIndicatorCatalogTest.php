<?php

namespace Tests\Feature\Adp;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicatorCatalogItem;
use App\Models\AdpObjective;
use App\Models\AdpPosition;
use App\Models\AdpPositionAssignment;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Adp\AdpAgreementPeriodGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdpIndicatorCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $controlGestion;

    private User $parametrizador;

    private AdpAgreement $agreement;

    private AdpAgreementPeriod $period;

    private AdpObjective $objective;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);

        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        IndicatorType::query()->where('clave_indicador', 'adp')->firstOrFail();
        // Asegurar que el indicator_type este habilitado globalmente
        IndicatorType::unguarded(fn () => IndicatorType::where('clave_indicador', 'adp')->update(['estado' => true]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS-ADP-A',
            'nombre' => 'Servicio de Salud A',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS-ADP-B',
            'nombre' => 'Servicio de Salud B',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $controlRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);

        $this->controlGestion = User::factory()->create([
            'role_id' => $controlRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->controlGestion->role()->associate($controlRole)->save();

        $this->parametrizador = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->parametrizador->role()->associate($paramRole)->save();
        $center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-ADP-SS1',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Establecimiento ADP',
            'nombre_corto' => 'ADP1',
            'cen_certification_id' => 1,
            'cen_adm_dependency_id' => 1,
            'nivel_atencion' => 'Primario',
            'tiene_urgencia' => 'No',
            'cen_emergency_type_id' => 1,
            'tipo_sapu' => 'No Aplica',
            'cen_provider_type_id' => 1,
            'estado_funcionamiento' => CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
            'nivel_complejidad' => 'Baja',
            'tipo_atencion' => 'Abierta',
        ]));

        $position = AdpPosition::unguarded(fn () => AdpPosition::create([
            'codigo' => 'DIR',
            'nombre' => 'Director(a)',
            'descripcion' => null,
        ]));

        $assignment = AdpPositionAssignment::unguarded(fn () => AdpPositionAssignment::create([
            'user_id' => $this->controlGestion->id,
            'adp_position_id' => $position->id,
            'cen_center_id' => $center->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]));

        $this->agreement = AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $assignment->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 1,
        ]));

        (new AdpAgreementPeriodGenerator)->replacePeriodsForAgreement($this->agreement);
        $this->agreement->load('periods');
        $this->period = $this->agreement->periods->first();

        $this->objective = AdpObjective::unguarded(fn () => AdpObjective::create([
            'adp_agreement_period_id' => $this->period->id,
            'clave' => 'O1',
            'nombre' => 'Objetivo 1',
            'orden' => 1,
        ]));
    }

    public function test_control_gestion_can_create_and_see_catalog_item_only_in_its_ss(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => Year::query()->first()]);

        $this->post(route('adp.catalog.store'), [
            'clave' => 'CAT-1',
            'nombre' => 'Indicador Catálogo 1',
            'calculation_method' => 'percent',
            'periodicidad' => 'ANUAL',
            'estado' => 1,
            'expression_json' => '[{"type":"var","key":"A"}]',
        ])->assertRedirect(route('adp.catalog.index'));

        $this->assertSame(1, AdpIndicatorCatalogItem::query()->count());
        $this->get(route('adp.catalog.index'))->assertStatus(200)->assertSee('CAT-1');

        AdpIndicatorCatalogItem::unguarded(fn () => AdpIndicatorCatalogItem::create([
            'cen_hie_dependency_id' => 2,
            'clave' => 'CAT-SS2',
            'nombre' => 'Indicador SS2',
            'periodicidad' => 'ANUAL',
            'calculation_method' => 'percent',
            'estado' => 1,
        ]));

        $this->get(route('adp.catalog.index'))->assertStatus(200)->assertDontSee('CAT-SS2');
    }

    public function test_parametrizador_cannot_create_catalog_item_by_default(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => Year::query()->first()]);

        $this->post(route('adp.catalog.store'), [
            'clave' => 'CAT-X',
            'nombre' => 'No debería crear',
            'calculation_method' => 'percent',
            'periodicidad' => 'ANUAL',
            'estado' => 1,
            'expression_json' => '[]',
        ])->assertStatus(403);
    }
}
