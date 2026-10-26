<?php

namespace Tests\Unit\Services\Metas;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetInstrumentDataEntryAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetInstrumentDataEntryAccessTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private MetIndicator $indicator;

    private CenCenter $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->createCenterCatalogDependencies();

        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-001',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Test',
            'nombre_corto' => 'HospTest',
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

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-ACCESS-01',
            'nombre' => 'Indicador acceso',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => false,
        ]));
    }

    /** @test */
    public function admin_puede_ver_carga_del_establecimiento(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($adminRole)->save();

        $this->assertTrue(app(MetInstrumentDataEntryAccess::class)->canViewCenterDataEntry(
            $user,
            $this->indicatorType,
            $this->indicator,
            $this->center,
            $this->year->id,
        ));
    }

    /** @test */
    public function parametrizador_no_puede_descargar_detalle_de_datos_en_carga(): void
    {
        $role = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $this->assertFalse(app(MetInstrumentDataEntryAccess::class)->canDownloadComplexFormulaDataDetail(
            $user,
            $this->indicatorType,
            $this->indicator,
            $this->center,
            $this->year->id,
        ));
    }

    /** @test */
    public function parametrizador_puede_descargar_detalle_desde_vista_resultados_por_establecimiento(): void
    {
        $role = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $this->assertTrue(app(MetInstrumentDataEntryAccess::class)->canDownloadComplexFormulaDataDetailForEstablishmentResultsView(
            $user,
            $this->indicatorType,
            $this->indicator,
            $this->center,
            $this->year->id,
        ));
    }

    /** @test */
    public function visualizador_no_puede_descargar_detalle_de_datos_en_carga(): void
    {
        $role = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $this->assertFalse(app(MetInstrumentDataEntryAccess::class)->canDownloadComplexFormulaDataDetail(
            $user,
            $this->indicatorType,
            $this->indicator,
            $this->center,
            $this->year->id,
        ));
    }

    /** @test */
    public function visualizador_puede_descargar_detalle_desde_vista_resultados_por_establecimiento(): void
    {
        $role = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $this->assertTrue(app(MetInstrumentDataEntryAccess::class)->canDownloadComplexFormulaDataDetailForEstablishmentResultsView(
            $user,
            $this->indicatorType,
            $this->indicator,
            $this->center,
            $this->year->id,
        ));
    }

    /** @test */
    public function referente_sin_asignacion_no_puede_descargar_detalle_de_datos(): void
    {
        $role = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $this->assertFalse(app(MetInstrumentDataEntryAccess::class)->canDownloadComplexFormulaDataDetail(
            $user,
            $this->indicatorType,
            $this->indicator,
            $this->center,
            $this->year->id,
        ));
    }

    /** @test */
    public function visualizador_no_puede_ver_carga_en_adosado_si_la_madre_tiene_indicador_sin_aplicar(): void
    {
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['aplica' => false]);

        $adosado = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-AD-002',
            'cod_antiguo' => null,
            'codigo_madre' => 'CEN-001',
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Otro adosado',
            'nombre_corto' => 'AD002',
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

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->center->id,
            'cen_center_adosado_id' => $adosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        $role = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $this->assertFalse(app(MetInstrumentDataEntryAccess::class)->canViewCenterDataEntry(
            $user,
            $this->indicatorType,
            $this->indicator,
            $adosado,
            $this->year->id,
        ));
    }

    private function createCenterCatalogDependencies(): void {}
}
