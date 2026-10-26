<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Metas\AppealType;
use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Livewire\MetasInstrument\IndicatorAppealComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetEstablishmentResponsible;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorAppeal;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetPreliminaryEvaluation;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class IndicatorAppealTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $controlGestion;

    private User $responsable;

    private CenCenter $center;

    private MetIndicator $indicator;

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

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->responsable = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->responsable->role()->associate($referenteRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'APELACION',
            'habilitado' => 1,
        ]));

        $this->createCenterCatalogDependencies();
        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-APL',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Apelación',
            'nombre_corto' => 'HospApl',
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

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-APL-01',
            'nombre' => 'Indicador apelación',
            'decimales' => 2,
            'meta_valor' => 50,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'orden' => 1,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
            'description' => '(A / B) * 100',
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'seleccionado' => true,
            'aplica' => true,
            'ponderacion' => 100,
            'meta_efectiva' => 50,
        ]));
    }

    /** @test */
    public function tabla_apelacion_muestra_metricas_de_evaluacion_preliminar(): void
    {
        MetPreliminaryEvaluation::unguarded(fn () => MetPreliminaryEvaluation::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'numerador' => 40,
            'denominador' => 50,
            'created_by' => $this->controlGestion->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorAppealComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Meta efectiva')
            ->assertSee('80.00')
            ->assertSee('100.00%')
            ->assertSee('100.00%')
            ->assertSee('Cumplimiento satisfactorio');
    }

    /** @test */
    public function control_gestion_designa_responsable_de_establecimiento(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openDesignateModal', $this->center->id)
            ->call('saveDesignation', $this->responsable->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_establishment_responsibles', [
            'cen_center_id' => $this->center->id,
            'user_id' => $this->responsable->id,
            'indicator_type_id' => $this->indicatorType->id,
        ]);
    }

    /** @test */
    public function responsable_designado_registra_apelacion(): void
    {
        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->responsable->id,
            'assigned_by' => $this->controlGestion->id,
        ]));

        $this->actingAs($this->responsable)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorAppealComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('openAppealForm', $this->indicator->id)
            ->set('appealType', AppealType::CausaExterna->value)
            ->set('motivo', 'Motivo de prueba con más de diez caracteres.')
            ->call('saveAppeal')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicator_appeals', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'appeal_type' => AppealType::CausaExterna->value,
        ]);

        $this->assertSame(1, MetIndicatorAppeal::query()->count());
    }

    /** @test */
    public function formulario_de_apelacion_se_muestra_en_modal(): void
    {
        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->responsable->id,
            'assigned_by' => $this->controlGestion->id,
        ]));

        $this->actingAs($this->responsable)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorAppealComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('openAppealForm', $this->indicator->id)
            ->assertSet('showForm', true)
            ->assertSee('modal-dialog modal-lg')
            ->assertSee('Apelación —');
    }

    /** @test */
    public function responsable_puede_eliminar_documento_adjunto_y_agregar_otro(): void
    {
        Storage::fake('local');

        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->responsable->id,
            'assigned_by' => $this->controlGestion->id,
        ]));

        $storedPath = 'metas/appeals/'.$this->year->id.'/'.$this->center->id.'/'.$this->indicator->id.'/viejo.pdf';
        Storage::disk('local')->put($storedPath, 'contenido viejo');

        MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'appeal_type' => AppealType::CausaExterna,
            'motivo' => 'Motivo de prueba con más de diez caracteres.',
            'archivos' => [['path' => $storedPath, 'name' => 'viejo.pdf']],
            'created_by' => $this->responsable->id,
        ]));

        $this->actingAs($this->responsable)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorAppealComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('openAppealForm', $this->indicator->id)
            ->call('removeExistingAttachment', 0)
            ->assertSet('existingAttachments', []);

        $this->assertFalse(Storage::disk('local')->exists($storedPath));
        $this->assertSame([], MetIndicatorAppeal::query()->first()?->archivos);
    }

    /** @test */
    public function usuario_puede_ver_apelacion_fuera_de_etapa_apelacion(): void
    {
        MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'appeal_type' => AppealType::CausaInterna,
            'motivo' => 'Motivo visible después del periodo de apelación.',
            'archivos' => [],
            'created_by' => $this->responsable->id,
        ]));

        IndicatorTypeSetting::query()
            ->where('indicator_type_id', $this->indicatorType->id)
            ->update(['etapa_vigente' => 'FINAL']);

        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create(['role_id' => $visualizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $visualizador->role()->associate($visualizadorRole)->save();

        $this->actingAs($visualizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorAppealComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('openAppealView', $this->indicator->id)
            ->assertSet('viewOnly', true)
            ->assertSee('Motivo visible después del periodo de apelación.')
            ->assertDontSee('Guardar apelación');
    }

    /** @test */
    public function usuario_puede_descargar_adjunto_fuera_de_etapa_apelacion(): void
    {
        Storage::fake('local');

        $storedPath = 'metas/appeals/'.$this->year->id.'/'.$this->center->id.'/'.$this->indicator->id.'/evidencia.txt';
        Storage::disk('local')->put($storedPath, 'evidencia de apelacion');

        $appeal = MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'appeal_type' => AppealType::CausaExterna,
            'motivo' => 'Motivo de prueba con más de diez caracteres.',
            'archivos' => [['path' => $storedPath, 'name' => 'evidencia.txt']],
            'created_by' => $this->responsable->id,
        ]));

        IndicatorTypeSetting::query()
            ->where('indicator_type_id', $this->indicatorType->id)
            ->update(['etapa_vigente' => 'FINAL']);

        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create(['role_id' => $visualizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $visualizador->role()->associate($visualizadorRole)->save();

        $this->actingAs($visualizador)->withSession(['indicator_year' => $this->year]);

        $response = $this->get(route('metas.instrument.appeal.attachment.download', [
            'indicatorType' => $this->indicatorType,
            'appeal' => $appeal->id,
            'attachmentIndex' => 0,
        ]));

        $response->assertOk();
        $response->assertDownload('evidencia.txt');
    }

    /** @test */
    public function descarga_adjunto_limita_peticiones_repetidas_por_minuto(): void
    {
        Storage::fake('local');

        $storedPath = 'metas/appeals/'.$this->year->id.'/'.$this->center->id.'/'.$this->indicator->id.'/evidencia.txt';
        Storage::disk('local')->put($storedPath, 'evidencia de apelacion');

        $appeal = MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'appeal_type' => AppealType::CausaExterna,
            'motivo' => 'Motivo de prueba con más de diez caracteres.',
            'archivos' => [['path' => $storedPath, 'name' => 'evidencia.txt']],
            'created_by' => $this->responsable->id,
        ]));

        $url = route('metas.instrument.appeal.attachment.download', [
            'indicatorType' => $this->indicatorType,
            'appeal' => $appeal->id,
            'attachmentIndex' => 0,
        ]);

        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($this->responsable)
                ->withSession(['indicator_year' => $this->year])
                ->get($url)
                ->assertOk();
        }

        $this->actingAs($this->responsable)
            ->withSession(['indicator_year' => $this->year])
            ->get($url)
            ->assertStatus(429);
    }

    private function createCenterCatalogDependencies(): void {}
}
