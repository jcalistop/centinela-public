<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Metas\AppealType;
use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\ComAction;
use App\Models\ComEvaluation;
use App\Models\ComIndicatorSetting;
use App\Models\ComSensitivityDependency;
use App\Models\ComVerifiableDocument;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetEstablishmentAgreement;
use App\Models\MetEstablishmentResponsible;
use App\Models\MetEstablishmentResult;
use App\Models\MetFieldSsConfig;
use App\Models\MetFinalEvaluation;
use App\Models\MetIndicator;
use App\Models\MetIndicatorAppeal;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMeasurementUnit;
use App\Models\MetMonthlyData;
use App\Models\MetPreliminaryEvaluation;
use App\Models\MetResult;
use App\Models\MetUnitType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Cen\CenCenterBrowseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Evidencia de aislamiento soft multi-tenancy por Servicio de Salud.
 *
 * Los tests `*_aisla_*` verifican GlobalScope / `hieFilter` / scopes de servicio.
 * Browse CEN sin scope BREAD aísla por SS a todo usuario con SS asignado (SDD-253).
 */
class CrossSsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $cgSs1;

    private User $cgSs2;

    private CenCenter $centerSs1;

    private CenCenter $centerSs2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        session(['indicator_year' => $this->year]);
        session()->forget(CenCenterBrowseService::SESSION_KEY);

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Uno',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Servicio de Salud Dos',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->cgSs1 = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->cgSs1->role()->associate($cgRole)->save();

        $this->cgSs2 = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 2,
        ]);
        $this->cgSs2->role()->associate($cgRole)->save();

        $this->centerSs1 = $this->createMinimalCenCenter([
            'codigo' => 'CEN-SS1',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro SS1',
            'nombre_corto' => 'C1',
        ]);

        $this->centerSs2 = $this->createMinimalCenCenter([
            'codigo' => 'CEN-SS2',
            'cen_hie_dependency_id' => 2,
            'nombre_oficial' => 'Centro SS2',
            'nombre_corto' => 'C2',
        ]);
    }

    /** @test */
    public function cen_center_hie_filter_aisla_ss_del_usuario_autenticado(): void
    {
        $this->actingAs($this->cgSs1);

        $ids = CenCenter::query()->hieFilter()->pluck('id')->all();

        $this->assertContains($this->centerSs1->id, $ids);
        $this->assertNotContains($this->centerSs2->id, $ids);
    }

    /** @test */
    public function met_monthly_data_aisla_ss(): void
    {
        $this->seedMetMonthlyDataBothSs();

        $this->actingAs($this->cgSs1);

        $ssIds = MetMonthlyData::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_monthly_data_admin_con_ss_tambien_aisla(): void
    {
        $this->seedMetMonthlyDataBothSs();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $admin->role()->associate($adminRole)->save();

        $this->actingAs($admin);

        $ssIds = MetMonthlyData::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_monthly_data_parametrizador_con_ss_tambien_aisla(): void
    {
        $this->seedMetMonthlyDataBothSs();

        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $param = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $param->role()->associate($paramRole)->save();

        $this->actingAs($param);

        $ssIds = MetMonthlyData::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_monthly_data_sin_auth_no_filtra(): void
    {
        $this->seedMetMonthlyDataBothSs();

        $ssIds = MetMonthlyData::query()->pluck('cen_hie_dependency_id')->unique()->sort()->values()->all();

        $this->assertSame([1, 2], $ssIds);
    }

    /** @test */
    public function met_result_aisla_ss(): void
    {
        $indicator = MetIndicator::factory()->create(['year_id' => $this->year->id]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs1->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'TRIM-1',
            'numerador' => 1,
            'denominador' => 1,
            'resultado' => 1,
            'cumple_meta' => false,
            'error_message' => null,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs2->id,
            'cen_hie_dependency_id' => 2,
            'year_id' => $this->year->id,
            'periodo' => 'TRIM-1',
            'numerador' => 1,
            'denominador' => 1,
            'resultado' => 1,
            'cumple_meta' => false,
            'error_message' => null,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetResult::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_establishment_result_aisla_ss(): void
    {
        $type = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->centerSs1->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'periodo' => 'TRIM-1',
            'resultado_global' => 99,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->centerSs2->id,
            'cen_hie_dependency_id' => 2,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'periodo' => 'TRIM-1',
            'resultado_global' => 99,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetEstablishmentResult::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function user_voyager_actor_manageable_aisla_ss_para_control_gestion(): void
    {
        $this->actingAs($this->cgSs1);

        $ids = User::query()->voyagerActorManageable()->pluck('id')->all();

        $this->assertContains($this->cgSs1->id, $ids);
        $this->assertNotContains($this->cgSs2->id, $ids);
    }

    /** @test */
    public function cen_center_browse_service_con_scope_hie_filter_aisla_ss(): void
    {
        $service = app(CenCenterBrowseService::class);

        $ids = $service->scopedCentersQueryForUser($this->cgSs1, 'hieFilter')->pluck('id')->all();

        $this->assertContains($this->centerSs1->id, $ids);
        $this->assertNotContains($this->centerSs2->id, $ids);
    }

    /**
     * Sin scope BREAD: referente con SS solo ve centros de su SS (SDD-253).
     *
     * @test
     */
    public function cen_center_browse_sin_scope_referente_aisla_ss(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($referenteRole)->save();

        $service = app(CenCenterBrowseService::class);
        $ids = $service->scopedCentersQueryForUser($referente, null)->pluck('id')->all();

        $this->assertContains($this->centerSs1->id, $ids);
        $this->assertNotContains($this->centerSs2->id, $ids);
    }

    /** @test */
    public function cen_center_browse_sin_scope_admin_aisla_ss(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $admin->role()->associate($adminRole)->save();

        $service = app(CenCenterBrowseService::class);
        $ids = $service->scopedCentersQueryForUser($admin, null)->pluck('id')->all();

        $this->assertContains($this->centerSs1->id, $ids);
        $this->assertNotContains($this->centerSs2->id, $ids);
    }

    /** @test */
    public function cen_center_browse_sin_scope_parametrizador_aisla_ss(): void
    {
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $param = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $param->role()->associate($paramRole)->save();

        $service = app(CenCenterBrowseService::class);
        $ids = $service->scopedCentersQueryForUser($param, null)->pluck('id')->all();

        $this->assertContains($this->centerSs1->id, $ids);
        $this->assertNotContains($this->centerSs2->id, $ids);
    }

    /**
     * SDD-300: el contexto de browse puede listar otro SS; la query de mutación/tenancy no.
     *
     * @test
     */
    public function cen_center_browse_session_lista_otro_ss_sin_abrir_tenancy_de_mutacion(): void
    {
        $service = app(CenCenterBrowseService::class);
        $this->actingAs($this->cgSs1);
        $service->applyRequestedSs($this->cgSs1, 2);

        $browseIds = $service->scopedCentersQueryForBrowse($this->cgSs1)->pluck('id')->all();
        $tenancyIds = $service->scopedCentersQueryForUser($this->cgSs1, 'hieFilter')->pluck('id')->all();

        $this->assertContains($this->centerSs2->id, $browseIds);
        $this->assertNotContains($this->centerSs1->id, $browseIds);
        $this->assertContains($this->centerSs1->id, $tenancyIds);
        $this->assertNotContains($this->centerSs2->id, $tenancyIds);
        $this->assertSame(1, $this->cgSs1->fresh()->cen_hie_dependency_id);
    }

    /**
     * SDD-300: la sesión de establecimientos no filtra instrumentos (Metas).
     *
     * @test
     */
    public function cen_center_browse_session_no_filtra_met_result(): void
    {
        $indicator = MetIndicator::factory()->create(['year_id' => $this->year->id]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs1->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'TRIM-1',
            'numerador' => 1,
            'denominador' => 1,
            'resultado' => 1,
            'cumple_meta' => false,
            'error_message' => null,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs2->id,
            'cen_hie_dependency_id' => 2,
            'year_id' => $this->year->id,
            'periodo' => 'TRIM-1',
            'numerador' => 1,
            'denominador' => 1,
            'resultado' => 1,
            'cumple_meta' => false,
            'error_message' => null,
        ]));

        $this->actingAs($this->cgSs1);
        app(CenCenterBrowseService::class)->applyRequestedSs($this->cgSs1, 2);

        $ids = MetResult::query()->pluck('cen_hie_dependency_id')->all();

        $this->assertContains(1, $ids);
        $this->assertNotContains(2, $ids);
    }

    /** @test */
    public function com_verifiable_document_aisla_ss(): void
    {
        $action = ComAction::factory()->create();
        $now = now();

        DB::table('com_verifiable_documents')->insert([
            [
                'com_action_id' => $action->id,
                'cen_hie_dependency_id' => 1,
                'archivos' => '[]',
                'descripcion' => 'MV',
                'verifiable_type' => 'EVALUACION',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'com_action_id' => $action->id,
                'cen_hie_dependency_id' => 2,
                'archivos' => '[]',
                'descripcion' => 'MV',
                'verifiable_type' => 'EVALUACION',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->actingAs($this->cgSs1);

        $ssIds = ComVerifiableDocument::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function com_sensitivity_dependency_aisla_ss(): void
    {
        $action = ComAction::factory()->create();
        $now = now();

        $tableId = DB::table('com_sensitivity_tables')->insertGetId([
            'com_action_id' => $action->id,
            'meta_numero' => 90,
            'valores' => json_encode([]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('com_sensitivity_dependencies')->insert([
            [
                'com_sensitivity_table_id' => $tableId,
                'com_action_id' => $action->id,
                'cen_hie_dependency_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'com_sensitivity_table_id' => $tableId,
                'com_action_id' => $action->id,
                'cen_hie_dependency_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->actingAs($this->cgSs1);

        $ssIds = ComSensitivityDependency::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /**
     * @test
     */
    public function com_indicator_setting_aisla_ss(): void
    {
        ComIndicatorSetting::factory()->forSs(1)->create();
        ComIndicatorSetting::factory()->forSs(2)->create();

        $this->actingAs($this->cgSs1);

        $ssIds = ComIndicatorSetting::query()->pluck('cen_hie_dependency_id')->unique()->sort()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /**
     * @test
     */
    public function indicator_type_setting_aisla_ss(): void
    {
        $type = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 2,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = IndicatorTypeSetting::query()->pluck('cen_hie_dependency_id')->unique()->sort()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /**
     * @test
     */
    public function met_field_ss_config_aisla_ss(): void
    {
        $field = $this->seedMetDataField();

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $field->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $field->id,
            'cen_hie_dependency_id' => 2,
            'year_id' => $this->year->id,
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetFieldSsConfig::query()->pluck('cen_hie_dependency_id')->unique()->sort()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /**
     * @test
     */
    public function met_measurement_unit_aisla_ss(): void
    {
        $unitType = MetUnitType::query()->first();
        if ($unitType === null) {
            $unitType = MetUnitType::unguarded(fn () => MetUnitType::create([
                'codigo' => 'UT-TEST',
                'nombre' => 'Unidad test',
                'orden' => 1,
                'activo' => true,
            ]));
        }

        MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->centerSs1->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'U1',
            'nombre' => 'Unidad SS1',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->centerSs2->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'U2',
            'nombre' => 'Unidad SS2',
            'activo' => true,
            'cen_hie_dependency_id' => 2,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetMeasurementUnit::query()->pluck('cen_hie_dependency_id')->unique()->sort()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /**
     * GlobalScope: no-admin aísla sin invocar hieFilter (retirado en SDD-222).
     *
     * @test
     */
    public function com_evaluation_aisla_ss(): void
    {
        ComEvaluation::factory()->forSs(1)->create();
        ComEvaluation::factory()->forSs(2)->create();

        $this->actingAs($this->cgSs1);

        $ssIds = ComEvaluation::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /**
     * Admin con SS asignado: también aísla (UI no preparada para cross-SS; DR-222-02).
     *
     * @test
     */
    public function com_evaluation_admin_con_ss_tambien_aisla(): void
    {
        ComEvaluation::factory()->forSs(1)->create();
        ComEvaluation::factory()->forSs(2)->create();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $admin->role()->associate($adminRole)->save();

        $this->actingAs($admin);

        $ssIds = ComEvaluation::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /**
     * Parametrizador con SS asignado: aísla (mismo contrato que admin en este trait).
     *
     * @test
     */
    public function com_evaluation_parametrizador_con_ss_tambien_aisla(): void
    {
        ComEvaluation::factory()->forSs(1)->create();
        ComEvaluation::factory()->forSs(2)->create();

        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $param = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $param->role()->associate($paramRole)->save();

        $this->actingAs($param);

        $ssIds = ComEvaluation::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /**
     * Sin Auth (jobs/console): early return — no filtra de más.
     *
     * @test
     */
    public function com_evaluation_sin_auth_no_filtra(): void
    {
        ComEvaluation::factory()->forSs(1)->create();
        ComEvaluation::factory()->forSs(2)->create();

        $ssIds = ComEvaluation::query()->pluck('cen_hie_dependency_id')->unique()->sort()->values()->all();

        $this->assertSame([1, 2], $ssIds);
    }

    /** @test */
    public function met_indicator_configuration_aisla_ss(): void
    {
        $this->actingAs($this->cgSs1);

        $indicator = MetIndicator::factory()->create(['year_id' => $this->year->id]);

        MetIndicatorConfiguration::factory()->create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerSs1->id,
        ]);

        MetIndicatorConfiguration::factory()->create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 2,
            'cen_center_id' => $this->centerSs2->id,
        ]);

        $ssIds = MetIndicatorConfiguration::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_indicator_referente_aisla_ss(): void
    {
        $this->seedMetIndicatorReferenteBothSs();

        $this->actingAs($this->cgSs1);

        $ssIds = MetIndicatorReferente::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_indicator_referente_admin_con_ss_tambien_aisla(): void
    {
        $this->seedMetIndicatorReferenteBothSs();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $admin->role()->associate($adminRole)->save();

        $this->actingAs($admin);

        $ssIds = MetIndicatorReferente::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_indicator_referente_parametrizador_con_ss_tambien_aisla(): void
    {
        $this->seedMetIndicatorReferenteBothSs();

        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $param = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $param->role()->associate($paramRole)->save();

        $this->actingAs($param);

        $ssIds = MetIndicatorReferente::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_establishment_responsible_aisla_ss(): void
    {
        $type = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $this->centerSs1->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->cgSs1->id,
            'assigned_by' => $this->cgSs1->id,
        ]));

        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $this->centerSs2->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 2,
            'user_id' => $this->cgSs2->id,
            'assigned_by' => $this->cgSs2->id,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetEstablishmentResponsible::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_establishment_agreement_aisla_ss(): void
    {
        $type = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        MetEstablishmentAgreement::unguarded(fn () => MetEstablishmentAgreement::create([
            'cen_center_id' => $this->centerSs1->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 1,
            'estado' => EstadoConvenioEstablecimiento::Enviado,
            'submitted_at' => now(),
            'submitted_by' => $this->cgSs1->id,
        ]));

        MetEstablishmentAgreement::unguarded(fn () => MetEstablishmentAgreement::create([
            'cen_center_id' => $this->centerSs2->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 2,
            'estado' => EstadoConvenioEstablecimiento::Enviado,
            'submitted_at' => now(),
            'submitted_by' => $this->cgSs2->id,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetEstablishmentAgreement::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_preliminary_evaluation_aisla_ss(): void
    {
        $indicator = MetIndicator::factory()->create(['year_id' => $this->year->id]);

        MetPreliminaryEvaluation::unguarded(fn () => MetPreliminaryEvaluation::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs1->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'numerador' => 40,
            'denominador' => 50,
            'created_by' => $this->cgSs1->id,
        ]));

        MetPreliminaryEvaluation::unguarded(fn () => MetPreliminaryEvaluation::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs2->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 2,
            'numerador' => 40,
            'denominador' => 50,
            'created_by' => $this->cgSs2->id,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetPreliminaryEvaluation::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_final_evaluation_aisla_ss(): void
    {
        $indicator = MetIndicator::factory()->create(['year_id' => $this->year->id]);

        MetFinalEvaluation::unguarded(fn () => MetFinalEvaluation::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs1->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'numerador' => 40,
            'denominador' => 50,
            'apelacion_acogida' => false,
            'created_by' => $this->cgSs1->id,
        ]));

        MetFinalEvaluation::unguarded(fn () => MetFinalEvaluation::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs2->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 2,
            'numerador' => 40,
            'denominador' => 50,
            'apelacion_acogida' => false,
            'created_by' => $this->cgSs2->id,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetFinalEvaluation::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_indicator_appeal_aisla_ss(): void
    {
        $indicator = MetIndicator::factory()->create(['year_id' => $this->year->id]);

        MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs1->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'appeal_type' => AppealType::CausaExterna,
            'motivo' => 'Motivo de prueba con más de diez caracteres.',
            'archivos' => [],
            'created_by' => $this->cgSs1->id,
        ]));

        MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerSs2->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 2,
            'appeal_type' => AppealType::CausaExterna,
            'motivo' => 'Motivo de prueba con más de diez caracteres.',
            'archivos' => [],
            'created_by' => $this->cgSs2->id,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetIndicatorAppeal::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_center_inclusion_aisla_ss(): void
    {
        $type = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerSs1->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerSs2->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 2,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetCenterInclusion::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    /** @test */
    public function met_center_adosado_aisla_ss(): void
    {
        $type = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $adosadoSs1 = $this->createMinimalCenCenter([
            'codigo' => 'CEN-ADO-SS1',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Adosado SS1',
            'nombre_corto' => 'ADO1',
        ]);

        $adosadoSs2 = $this->createMinimalCenCenter([
            'codigo' => 'CEN-ADO-SS2',
            'cen_hie_dependency_id' => 2,
            'nombre_oficial' => 'Adosado SS2',
            'nombre_corto' => 'ADO2',
        ]);

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerSs1->id,
            'cen_center_adosado_id' => $adosadoSs1->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerSs2->id,
            'cen_center_adosado_id' => $adosadoSs2->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 2,
        ]));

        $this->actingAs($this->cgSs1);

        $ssIds = MetCenterAdosado::query()->pluck('cen_hie_dependency_id')->unique()->values()->all();

        $this->assertSame([1], $ssIds);
    }

    private function seedMetIndicatorReferenteBothSs(): void
    {
        $indicator = MetIndicator::factory()->create(['year_id' => $this->year->id]);

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->cgSs1->id,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 2,
            'user_id' => $this->cgSs2->id,
        ]));
    }

    private function seedMetMonthlyDataBothSs(): void
    {
        $field = MetDataField::factory()->create();

        MetMonthlyData::factory()->create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $this->centerSs1->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10,
        ]);

        MetMonthlyData::factory()->create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $this->centerSs2->id,
            'cen_hie_dependency_id' => 2,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 20,
        ]);
    }

    private function seedMetDataField(): MetDataField
    {
        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'clave' => 'MET-TENANCY-'.uniqid(),
            'nombre' => 'Indicador tenancy',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        return MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));
    }
}
