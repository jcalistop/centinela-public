<?php

namespace Tests\Feature\Adp;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpMidtermEvaluation;
use App\Models\AdpMonthlyData;
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
use App\Services\Adp\AdpMidtermEvaluationValidator;
use App\Services\Adp\AdpMonthlyDataValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdpMonthlyDataTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $admin;

    private CenCenter $center;

    private AdpPosition $position;

    private AdpPositionAssignment $assignment;

    private AdpAgreement $agreement;

    private AdpAgreementPeriod $period;

    private AdpIndicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);

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

        $this->agreement = AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $this->assignment->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 1,
        ]));

        (new AdpAgreementPeriodGenerator)->replacePeriodsForAgreement($this->agreement);
        $this->agreement->load('periods');
        $this->period = $this->agreement->periods->first();

        $objective = AdpObjective::unguarded(fn () => AdpObjective::create([
            'adp_agreement_period_id' => $this->period->id,
            'clave' => 'O1',
            'nombre' => 'Objetivo 1',
            'orden' => 1,
        ]));

        $this->indicator = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $objective->id,
            'clave' => 'I1',
            'nombre' => 'Indicador 1',
        ]));
    }

    public function test_monthly_data_normalizes_to_first_of_month(): void
    {
        AdpMonthlyData::unguarded(fn () => AdpMonthlyData::create([
            'adp_indicator_id' => $this->indicator->id,
            'adp_agreement_period_id' => $this->period->id,
            'month_date' => '2026-06-15',
            'valor' => 1.5,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $row = AdpMonthlyData::query()->first();
        $this->assertSame('2026-06-01', $row->month_date->toDateString());
    }

    public function test_monthly_data_rejected_outside_period(): void
    {
        $this->expectException(ValidationException::class);

        AdpMonthlyData::unguarded(fn () => AdpMonthlyData::create([
            'adp_indicator_id' => $this->indicator->id,
            'adp_agreement_period_id' => $this->period->id,
            'month_date' => '2025-06-01',
            'valor' => 1,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
        ]));
    }

    public function test_monthly_data_validator_rejects_non_overlapping_month(): void
    {
        $this->expectException(ValidationException::class);
        AdpMonthlyDataValidator::assertMonthDateInPeriod(
            Carbon::parse('2027-01-01'),
            $this->period
        );
    }

    public function test_http_store_monthly_succeeds_for_allowed_month(): void
    {
        $this->post(
            route('adp.self_evaluation.indicator.monthly.store', [
                $this->agreement,
                $this->period,
                $this->indicator,
            ]),
            [
                'month_date' => '2026-03-01',
                'valor' => '2.25',
                'observacion' => 'ok',
            ]
        )->assertRedirect();

        $row = AdpMonthlyData::query()->where('adp_indicator_id', $this->indicator->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-03-01', $row->month_date->toDateString());
        $this->assertEqualsWithDelta(2.25, (float) $row->valor, 0.0001);
    }

    public function test_http_store_monthly_rejects_disallowed_month(): void
    {
        $this->post(
            route('adp.self_evaluation.indicator.monthly.store', [
                $this->agreement,
                $this->period,
                $this->indicator,
            ]),
            [
                'month_date' => '2027-01-01',
                'valor' => '1',
            ]
        )->assertSessionHasErrors('month_date');
    }

    public function test_midterm_accepted_within_six_month_window(): void
    {
        AdpMidtermEvaluation::unguarded(fn () => AdpMidtermEvaluation::create([
            'adp_agreement_period_id' => $this->period->id,
            'evaluated_on' => '2026-07-01',
            'status' => 'submitted',
            'cen_hie_dependency_id' => 1,
        ]));

        $this->assertDatabaseHas('adp_midterm_evaluations', [
            'adp_agreement_period_id' => $this->period->id,
        ]);
    }

    public function test_midterm_rejected_outside_tolerance(): void
    {
        $this->expectException(ValidationException::class);

        AdpMidtermEvaluation::unguarded(fn () => AdpMidtermEvaluation::create([
            'adp_agreement_period_id' => $this->period->id,
            'evaluated_on' => '2026-09-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 1,
        ]));
    }

    public function test_midterm_rejected_outside_period_range(): void
    {
        $periodStart = Carbon::parse('2026-03-15');
        $periodEnd = Carbon::parse('2027-03-14');
        $this->period->start_date = $periodStart;
        $this->period->end_date = $periodEnd;
        $this->period->save();

        $expected = $this->period->fresh()->expectedMidtermDate();
        $tooEarly = $expected->copy()->subDays(AdpMidtermEvaluationValidator::TOLERANCE_DAYS + 3);

        $this->expectException(ValidationException::class);

        AdpMidtermEvaluation::unguarded(fn () => AdpMidtermEvaluation::create([
            'adp_agreement_period_id' => $this->period->id,
            'evaluated_on' => $tooEarly->toDateString(),
            'status' => 'draft',
            'cen_hie_dependency_id' => 1,
        ]));
    }

    public function test_self_evaluation_agreements_page_ok(): void
    {
        $this->get(route('adp.self_evaluation.agreements'))
            ->assertOk()
            ->assertSee('Autoevaluación', false);
    }

    public function test_monthly_data_hidden_for_other_ss_under_global_scope(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS-2',
            'nombre' => 'Otro',
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

        $agr2 = AdpAgreement::unguarded(fn () => AdpAgreement::create([
            'adp_position_assignment_id' => $assignment2->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'draft',
            'cen_hie_dependency_id' => 2,
        ]));

        (new AdpAgreementPeriodGenerator)->replacePeriodsForAgreement($agr2);
        $p2 = $agr2->periods()->first();
        $obj2 = AdpObjective::unguarded(fn () => AdpObjective::create([
            'adp_agreement_period_id' => $p2->id,
            'clave' => 'O',
            'nombre' => 'O',
            'orden' => 1,
        ]));
        $ind2 = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $obj2->id,
            'clave' => 'X',
            'nombre' => 'X',
        ]));

        AdpMonthlyData::unguarded(fn () => AdpMonthlyData::create([
            'adp_indicator_id' => $ind2->id,
            'adp_agreement_period_id' => $p2->id,
            'month_date' => '2026-02-01',
            'valor' => 9,
            'cen_center_id' => $center2->id,
            'cen_hie_dependency_id' => 2,
        ]));

        $roleReferente = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $roleReferente->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($roleReferente)->save();

        $this->actingAs($referente);

        $this->assertSame(0, AdpMonthlyData::query()->count());
    }

    private function seedCenterCatalog(): void {}
}
