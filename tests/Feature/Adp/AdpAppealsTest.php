<?php

namespace Tests\Feature\Adp;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorAppeal;
use App\Models\AdpIndicatorFormula;
use App\Models\AdpIndicatorTarget;
use App\Models\AdpMonthlyData;
use App\Models\AdpObjective;
use App\Models\AdpPosition;
use App\Models\AdpPositionAssignment;
use App\Models\AdpPreliminarySnapshot;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Adp\AdpAgreementPeriodGenerator;
use App\Services\Adp\AdpAppealService;
use App\Services\Adp\AdpCalculationService;
use App\Services\Adp\AdpFinalResultService;
use App\Services\Adp\AdpPreliminarySnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdpAppealsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AdpAgreement $agreement;

    private AdpAgreementPeriod $period;

    private AdpIndicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);

        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
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
        $this->actingAs($this->admin)->withSession(['indicator_year' => Year::query()->first()]);
        $center = CenCenter::unguarded(fn () => CenCenter::create([
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

        $position = AdpPosition::unguarded(fn () => AdpPosition::create([
            'codigo' => 'DIR',
            'nombre' => 'Director(a)',
            'descripcion' => null,
        ]));

        $assignment = AdpPositionAssignment::unguarded(fn () => AdpPositionAssignment::create([
            'user_id' => $this->admin->id,
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
            'meta_valor' => 100,
            'meta_operador' => '>=',
            'umbral_inferior' => 0,
            'umbral_superior' => 100,
            'calculation_method' => 'percent',
        ]));

        AdpIndicatorFormula::unguarded(fn () => AdpIndicatorFormula::create([
            'adp_indicator_id' => $this->indicator->id,
            'expression' => [['type' => 'var', 'key' => 'A']],
            'description' => 'A',
        ]));

        AdpIndicatorTarget::unguarded(fn () => AdpIndicatorTarget::create([
            'adp_indicator_id' => $this->indicator->id,
            'weight_percent' => 100,
            'target_value' => 100,
            'target_operador' => '>=',
        ]));

        AdpMonthlyData::unguarded(fn () => AdpMonthlyData::create([
            'adp_indicator_id' => $this->indicator->id,
            'adp_agreement_period_id' => $this->period->id,
            'month_date' => '2026-03-01',
            'valor' => 90,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
        ]));
    }

    public function test_preliminary_snapshot_requires_calculation(): void
    {
        $this->expectException(ValidationException::class);
        app(AdpPreliminarySnapshotService::class)->createSnapshot($this->period, $this->admin->id);
    }

    public function test_cannot_appeal_without_preliminary(): void
    {
        app(AdpCalculationService::class)->recalculatePeriod($this->period);

        $this->expectException(ValidationException::class);
        app(AdpAppealService::class)->createAppeal($this->period, $this->indicator, 'Motivo de prueba');
    }

    public function test_cannot_publish_final_without_preliminary_or_documented_reason(): void
    {
        app(AdpCalculationService::class)->recalculatePeriod($this->period);

        $this->expectException(ValidationException::class);
        app(AdpFinalResultService::class)->publishFinal($this->period, $this->admin->id, null);
    }

    public function test_can_publish_final_without_preliminary_with_documented_reason(): void
    {
        app(AdpCalculationService::class)->recalculatePeriod($this->period);

        $final = app(AdpFinalResultService::class)->publishFinal(
            $this->period,
            $this->admin->id,
            'Decisión excepcional documentada para prueba automatizada.',
        );

        $this->assertTrue($final->published_without_preliminary);
        $this->assertNotNull($final->published_without_preliminary_reason);
        $this->assertSame(1, $final->rows()->count());
    }

    public function test_happy_path_preliminary_appeal_resolve_final_override(): void
    {
        app(AdpCalculationService::class)->recalculatePeriod($this->period);

        $snap = app(AdpPreliminarySnapshotService::class)->createSnapshot($this->period, $this->admin->id);
        $this->assertDatabaseHas('adp_preliminary_snapshots', [
            'adp_agreement_period_id' => $this->period->id,
            'version' => 1,
        ]);
        $this->assertSame(1, $snap->rows()->count());

        $appeal = app(AdpAppealService::class)->createAppeal($this->period, $this->indicator, 'Discrepancia con evidencia');
        $this->assertSame(AdpIndicatorAppeal::STATUS_PENDING, $appeal->status);

        app(AdpAppealService::class)->resolveAppeal(
            $appeal,
            AdpIndicatorAppeal::STATUS_ACCEPTED,
            'Se acepta por documentación presentada.',
            $this->admin->id,
        );

        $final = app(AdpFinalResultService::class)->publishFinal($this->period, $this->admin->id, null);
        $this->assertFalse($final->published_without_preliminary);
        $this->assertSame((int) $snap->id, (int) $final->adp_preliminary_snapshot_id);

        app(AdpFinalResultService::class)->applyComplianceOverride(
            $final,
            $this->indicator,
            '95.0000',
            'Ajuste post-apelación aceptada',
            $this->admin->id,
        );

        $this->assertDatabaseHas('adp_result_overrides', [
            'adp_final_result_id' => $final->id,
            'adp_indicator_id' => $this->indicator->id,
        ]);

        $row = $final->rows()->where('adp_indicator_id', $this->indicator->id)->first();
        $this->assertNotNull($row);
        $this->assertTrue($row->is_overridden);
        $this->assertEqualsWithDelta(95.0, (float) $row->final_compliance_percent, 0.01);
    }

    public function test_preliminary_store_route(): void
    {
        app(AdpCalculationService::class)->recalculatePeriod($this->period);

        $this->post(route('adp.self_evaluation.preliminary.store', [$this->agreement, $this->period]))
            ->assertRedirect(route('adp.self_evaluation.preliminary', [$this->agreement, $this->period]));

        $this->assertSame(1, AdpPreliminarySnapshot::query()->where('adp_agreement_period_id', $this->period->id)->count());
    }

    public function test_override_requires_reason(): void
    {
        app(AdpCalculationService::class)->recalculatePeriod($this->period);
        app(AdpPreliminarySnapshotService::class)->createSnapshot($this->period, $this->admin->id);
        $final = app(AdpFinalResultService::class)->publishFinal($this->period, $this->admin->id, null);

        $this->expectException(ValidationException::class);
        app(AdpFinalResultService::class)->applyComplianceOverride(
            $final,
            $this->indicator,
            '88',
            '',
            $this->admin->id,
        );
    }
}
