<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\IndicatorType\EtapaVigente;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\MetEstablishmentResponsible;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetStageAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetStageAccessTest extends TestCase
{
    use RefreshDatabase;

    private MetStageAccessService $access;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(MetStageAccessService::class);
        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
    }

    /** @test */
    public function formulacion_y_autoevaluacion_permiten_carga_mensual(): void
    {
        $this->assertTrue($this->access->canWriteMonthlyData(EtapaVigente::Formulacion));
        $this->assertTrue($this->access->canWriteMonthlyData(EtapaVigente::Autoevaluacion));
        $this->assertFalse($this->access->canWriteMonthlyData(EtapaVigente::Preliminar));
        $this->assertFalse($this->access->canWriteMonthlyData(EtapaVigente::Apelacion));
    }

    /** @test */
    public function etapas_de_captura_respetan_etapa_vigente(): void
    {
        $this->assertTrue($this->access->canWritePreliminary(EtapaVigente::Preliminar));
        $this->assertFalse($this->access->canWritePreliminary(EtapaVigente::Final));

        $this->assertTrue($this->access->canWriteAppeals(EtapaVigente::Apelacion));
        $this->assertTrue($this->access->canWriteFinal(EtapaVigente::Final));
    }

    /** @test */
    public function control_gestion_puede_designar_responsable_de_establecimiento(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $cg = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $cg->role()->associate($cgRole)->save();

        $referente = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $referente->role()->associate($referenteRole)->save();

        $this->assertTrue($this->access->canDesignateEstablishmentResponsible($cg));
        $this->assertFalse($this->access->canDesignateEstablishmentResponsible($referente));
    }

    /** @test */
    public function solo_usuario_designado_puede_apelar(): void
    {
        $responsableRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $responsable = User::factory()->create(['role_id' => $responsableRole->id, 'cen_hie_dependency_id' => 1]);
        $responsable->role()->associate($responsableRole)->save();

        $otro = User::factory()->create(['role_id' => $responsableRole->id, 'cen_hie_dependency_id' => 1]);
        $otro->role()->associate($responsableRole)->save();

        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->createCenterCatalogDependencies();
        $center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-ACC',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Access',
            'nombre_corto' => 'HospAcc',
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

        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $responsable->id,
        ]));

        $this->assertTrue($this->access->canAppeal($responsable, $center->id, $this->year->id, $indicatorType->id));
        $this->assertFalse($this->access->canAppeal($otro, $center->id, $this->year->id, $indicatorType->id));
    }

    private function createCenterCatalogDependencies(): void {}
}
