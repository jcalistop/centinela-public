<?php

namespace Tests\Feature\Adp;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorFormula;
use App\Models\AdpIndicatorTarget;
use App\Models\AdpMonthlyData;
use App\Models\AdpObjective;
use App\Models\AdpPosition;
use App\Models\AdpPositionAssignment;
use App\Models\AdpSensitivityTable;
use App\Models\AdpSensitivityTableRow;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\ComSensitivityTable;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Adp\AdpAgreementPeriodGenerator;
use App\Services\Adp\AdpCalculationService;
use App\Services\Adp\AdpSensitivityRowsValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdpCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AdpAgreement $agreement;

    private AdpAgreementPeriod $period;

    private AdpIndicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_recalculate_percent_persists_indicator_and_global_result(): void
    {
        $svc = app(AdpCalculationService::class);
        $periodResult = $svc->recalculatePeriod($this->period);

        $this->assertSame(1, $periodResult->n_calculated);
        $this->assertSame(0, $periodResult->n_failed);
        $this->assertEqualsWithDelta(90.0, (float) $periodResult->weighted_global_percent, 0.01);

        $row = $this->indicator->indicatorResults()->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(90.0, (float) $row->raw_result, 0.01);
        $this->assertEqualsWithDelta(90.0, (float) $row->compliance_percent, 0.01);
    }

    public function test_recalculate_is_idempotent(): void
    {
        $svc = app(AdpCalculationService::class);
        $a = $svc->recalculatePeriod($this->period);
        $b = $svc->recalculatePeriod($this->period);

        $this->assertSame($a->id, $b->id);
        $this->assertEqualsWithDelta((float) $a->weighted_global_percent, (float) $b->weighted_global_percent, 0.0001);
    }

    public function test_incomplete_weights_block_calculation(): void
    {
        $objective = AdpObjective::unguarded(fn () => AdpObjective::create([
            'adp_agreement_period_id' => $this->period->id,
            'clave' => 'O2',
            'nombre' => 'Objetivo 2',
            'orden' => 2,
        ]));

        AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $objective->id,
            'clave' => 'I2',
            'nombre' => 'Indicador 2',
            'calculation_method' => 'percent',
        ]));

        $this->expectException(ValidationException::class);
        app(AdpCalculationService::class)->recalculatePeriod($this->period);
    }

    public function test_sensitivity_rows_overlap_rejected(): void
    {
        $r1 = ComSensitivityTable::normalizeValorRule([
            'valor_minimo' => 0,
            'operador_minimo' => '>=',
            'valor_maximo' => 100,
            'operador_maximo' => '<=',
            'cumplimiento' => 50,
        ]);
        $r2 = ComSensitivityTable::normalizeValorRule([
            'valor_minimo' => 50,
            'operador_minimo' => '>=',
            'valor_maximo' => 150,
            'operador_maximo' => '<=',
            'cumplimiento' => 80,
        ]);

        $this->expectException(ValidationException::class);
        AdpSensitivityRowsValidator::validate([$r1, $r2]);
    }

    public function test_recalculate_sensitivity_uses_table(): void
    {
        $this->indicator->update(['calculation_method' => 'sensitivity']);

        $table = AdpSensitivityTable::unguarded(fn () => AdpSensitivityTable::create([
            'adp_indicator_id' => $this->indicator->id,
            'nombre' => 'TS',
        ]));

        AdpSensitivityTableRow::unguarded(fn () => AdpSensitivityTableRow::create([
            'adp_sensitivity_table_id' => $table->id,
            'orden' => 1,
            'valor_minimo' => null,
            'operador_minimo' => '>=',
            'valor_maximo' => 100,
            'operador_maximo' => '<=',
            'cumplimiento' => 77.5,
        ]));

        $periodResult = app(AdpCalculationService::class)->recalculatePeriod($this->period);

        $this->assertEqualsWithDelta(77.5, (float) $periodResult->weighted_global_percent, 0.01);
    }

    public function test_recalculate_dichotomic(): void
    {
        $this->indicator->update([
            'calculation_method' => 'dichotomic',
            'meta_valor' => 50,
            'meta_operador' => '>=',
        ]);

        $this->indicator->target()->update([
            'target_value' => null,
            'target_operador' => null,
        ]);

        AdpMonthlyData::unguarded(fn () => AdpMonthlyData::query()->update(['valor' => 60]));

        $periodResult = app(AdpCalculationService::class)->recalculatePeriod($this->period);

        $this->assertEqualsWithDelta(100.0, (float) $periodResult->weighted_global_percent, 0.01);
    }
}
