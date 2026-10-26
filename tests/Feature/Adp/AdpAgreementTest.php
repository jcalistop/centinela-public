<?php

namespace Tests\Feature\Adp;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorFormula;
use App\Models\AdpIndicatorTarget;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdpAgreementTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $admin;

    private CenCenter $center;

    private AdpPosition $position;

    private AdpPositionAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        IndicatorType::query()->where('clave_indicador', 'adp')->firstOrFail();

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

        $this->assignment = AdpPositionAssignment::unguarded(fn () => AdpPositionAssignment::create([
            'user_id' => $this->admin->id,
            'adp_position_id' => $this->position->id,
            'cen_center_id' => $this->center->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]));
    }

    public function test_generates_three_mobile_annual_periods(): void
    {
        $agreement = AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $this->assignment->id,
            'duration_years' => 3,
            'agreement_start' => '2026-03-15',
            'status' => 'draft',
            'cen_hie_dependency_id' => 1,
        ]));

        (new AdpAgreementPeriodGenerator)->replacePeriodsForAgreement($agreement);

        $agreement->load('periods');
        $this->assertCount(3, $agreement->periods);
        $this->assertSame(1, $agreement->periods[0]->sequence);
        $this->assertSame('2026-03-15', $agreement->periods[0]->start_date->toDateString());
        $this->assertSame('2027-03-14', $agreement->periods[0]->end_date->toDateString());
        $this->assertSame('2027-03-15', $agreement->periods[1]->start_date->toDateString());
        $this->assertSame('2028-03-14', $agreement->periods[1]->end_date->toDateString());
        $this->assertSame('2028-03-15', $agreement->periods[2]->start_date->toDateString());
        $this->assertSame('2029-03-14', $agreement->periods[2]->end_date->toDateString());
    }

    public function test_target_rejects_weight_below_ten_percent(): void
    {
        $indicator = $this->makeIndicatorInFirstPeriod();

        $this->expectException(ValidationException::class);

        AdpIndicatorTarget::unguarded(fn () => AdpIndicatorTarget::create([
            'adp_indicator_id' => $indicator->id,
            'weight_percent' => 9.99,
        ]));
    }

    public function test_target_rejects_sum_not_100_when_all_indicators_weighted(): void
    {
        $period = $this->firstPeriodWithTwoIndicators();

        $i1 = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $period->objectives->first()->id,
            'clave' => 'A',
            'nombre' => 'Indicador A',
        ]));
        $i2 = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $period->objectives->first()->id,
            'clave' => 'B',
            'nombre' => 'Indicador B',
        ]));

        AdpIndicatorTarget::unguarded(fn () => AdpIndicatorTarget::create([
            'adp_indicator_id' => $i1->id,
            'weight_percent' => 50.0,
        ]));

        $this->expectException(ValidationException::class);

        AdpIndicatorTarget::unguarded(fn () => AdpIndicatorTarget::create([
            'adp_indicator_id' => $i2->id,
            'weight_percent' => 49.0,
        ]));
    }

    public function test_target_allows_partial_weights_until_all_defined(): void
    {
        $period = $this->firstPeriodWithTwoIndicators();

        $i1 = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $period->objectives->first()->id,
            'clave' => 'A',
            'nombre' => 'Indicador A',
        ]));
        $i2 = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $period->objectives->first()->id,
            'clave' => 'B',
            'nombre' => 'Indicador B',
        ]));

        AdpIndicatorTarget::unguarded(fn () => AdpIndicatorTarget::create([
            'adp_indicator_id' => $i1->id,
            'weight_percent' => 50.0,
        ]));

        AdpIndicatorTarget::unguarded(fn () => AdpIndicatorTarget::create([
            'adp_indicator_id' => $i2->id,
            'weight_percent' => null,
        ]));

        $this->assertDatabaseHas('adp_indicator_targets', ['adp_indicator_id' => $i2->id]);
    }

    public function test_target_accepts_two_indicators_at_fifty_percent(): void
    {
        $period = $this->firstPeriodWithTwoIndicators();

        $i1 = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $period->objectives->first()->id,
            'clave' => 'A',
            'nombre' => 'Indicador A',
        ]));
        $i2 = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $period->objectives->first()->id,
            'clave' => 'B',
            'nombre' => 'Indicador B',
        ]));

        AdpIndicatorTarget::unguarded(fn () => AdpIndicatorTarget::create([
            'adp_indicator_id' => $i1->id,
            'weight_percent' => 50.0,
        ]));
        AdpIndicatorTarget::unguarded(fn () => AdpIndicatorTarget::create([
            'adp_indicator_id' => $i2->id,
            'weight_percent' => 50.0,
        ]));

        $this->assertDatabaseCount('adp_indicator_targets', 2);
    }

    public function test_formula_tokens_persist_like_metas_pattern(): void
    {
        $indicator = $this->makeIndicatorInFirstPeriod();

        AdpIndicatorFormula::unguarded(fn () => AdpIndicatorFormula::create([
            'adp_indicator_id' => $indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
            ],
            'description' => 'A / B',
        ]));

        $this->assertDatabaseHas('adp_indicator_formulas', [
            'adp_indicator_id' => $indicator->id,
            'description' => 'A / B',
        ]);
    }

    public function test_agreement_denormalizes_hie_from_assignment(): void
    {
        $agreement = AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $this->assignment->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 0,
        ]));

        $this->assertSame(1, (int) $agreement->fresh()->cen_hie_dependency_id);
    }

    private function seedCenterCatalog(): void {}

    private function makeAgreementWithOnePeriod(): AdpAgreement
    {
        $agreement = AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $this->assignment->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 1,
        ]));

        (new AdpAgreementPeriodGenerator)->replacePeriodsForAgreement($agreement);

        return $agreement->load('periods');
    }

    private function makeIndicatorInFirstPeriod(): AdpIndicator
    {
        $agreement = $this->makeAgreementWithOnePeriod();
        $period = $agreement->periods->first();

        $objective = AdpObjective::unguarded(fn () => AdpObjective::create([
            'adp_agreement_period_id' => $period->id,
            'clave' => 'O1',
            'nombre' => 'Objetivo 1',
            'orden' => 1,
        ]));

        return AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $objective->id,
            'clave' => 'I1',
            'nombre' => 'Indicador 1',
        ]));
    }

    private function firstPeriodWithTwoIndicators(): AdpAgreementPeriod
    {
        $agreement = $this->makeAgreementWithOnePeriod();
        $period = $agreement->periods->first();

        AdpObjective::unguarded(fn () => AdpObjective::create([
            'adp_agreement_period_id' => $period->id,
            'clave' => 'O1',
            'nombre' => 'Objetivo 1',
            'orden' => 1,
        ]));

        return $period->fresh(['objectives']);
    }

    public function test_non_admin_cannot_view_agreement_outside_ss_via_policy(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS-2',
            'nombre' => 'Otro SS',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center2 = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-2',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 2,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Est 2',
            'nombre_corto' => 'E2',
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

        $assignment2 = AdpPositionAssignment::unguarded(fn () => AdpPositionAssignment::create([
            'user_id' => $this->admin->id,
            'adp_position_id' => $this->position->id,
            'cen_center_id' => $center2->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]));

        $agreementOther = AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $assignment2->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 2,
        ]));

        $roleReferente = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $roleReferente->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($roleReferente)->save();

        $this->actingAs($referente);

        $this->assertFalse($referente->can('read', $agreementOther));
    }

    public function test_non_admin_sees_only_agreements_in_their_ss_under_global_scope(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS-2',
            'nombre' => 'Otro SS',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center2 = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-2B',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 2,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Est 2',
            'nombre_corto' => 'E2',
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

        $assignment2 = AdpPositionAssignment::unguarded(fn () => AdpPositionAssignment::create([
            'user_id' => $this->admin->id,
            'adp_position_id' => $this->position->id,
            'cen_center_id' => $center2->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]));

        AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $this->assignment->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 1,
        ]));

        AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $assignment2->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 2,
        ]));

        $roleReferente = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $roleReferente->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($roleReferente)->save();

        $this->actingAs($referente);

        $this->assertSame(1, AdpAgreement::query()->count());
    }
}
