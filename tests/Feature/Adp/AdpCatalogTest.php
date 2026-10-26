<?php

namespace Tests\Feature\Adp;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\AdpPosition;
use App\Models\AdpPositionAssignment;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdpCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorTypeAdp;

    private CenCenter $center;

    private AdpPosition $position;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->indicatorTypeAdp = IndicatorType::query()->where('clave_indicador', 'adp')->firstOrFail();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS-ADP',
            'nombre' => 'Servicio de Salud ADP Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $this->actingAs($this->admin)->withSession(['indicator_year' => $this->year]);

        $this->seedCenterCatalog();

        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-ADP-1',
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

        $this->position = AdpPosition::unguarded(fn () => AdpPosition::create([
            'codigo' => 'DIR',
            'nombre' => 'Director(a)',
            'descripcion' => null,
        ]));
    }

    public function test_admin_can_open_adp_hub(): void
    {
        $this->get(route('adp.instrument.index'))
            ->assertOk()
            ->assertSee('Alta Dirección Pública', false);
    }

    public function test_assignment_denormalizes_cen_hie_dependency_from_center(): void
    {
        $assignment = AdpPositionAssignment::unguarded(fn () => AdpPositionAssignment::create([
            'user_id' => $this->admin->id,
            'adp_position_id' => $this->position->id,
            'cen_center_id' => $this->center->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]));

        $this->assertSame(1, (int) $assignment->cen_hie_dependency_id);
    }

    public function test_assignment_rejects_end_before_start(): void
    {
        $this->expectException(ValidationException::class);

        AdpPositionAssignment::unguarded(fn () => AdpPositionAssignment::create([
            'user_id' => $this->admin->id,
            'adp_position_id' => $this->position->id,
            'cen_center_id' => $this->center->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01',
        ]));
    }

    public function test_referente_cannot_assign_center_outside_their_ss(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS-OTRO',
            'nombre' => 'Otro SS',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $centerOtro = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-OTRO',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 2,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Otro establecimiento',
            'nombre_corto' => 'OTRO',
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

        $roleReferente = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $roleReferente->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($roleReferente)->save();

        $this->expectException(ValidationException::class);

        $this->actingAs($referente);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::query()->updateOrCreate(
            [
                'indicator_type_id' => $this->indicatorTypeAdp->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            ['habilitado' => true, 'etapa_vigente' => 'CERRADO']
        ));

        AdpPositionAssignment::unguarded(fn () => AdpPositionAssignment::create([
            'user_id' => $referente->id,
            'adp_position_id' => $this->position->id,
            'cen_center_id' => $centerOtro->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]));
    }

    private function seedCenterCatalog(): void {}
}
