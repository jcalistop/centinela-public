<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\Metas\AppealType;
use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Livewire\MetasInstrument\EstablishmentTimelineComponent;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentAgreement;
use App\Models\MetEstablishmentResponsible;
use App\Models\MetIndicator;
use App\Models\MetIndicatorAppeal;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetEstablishmentTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class MetEstablishmentTimelineTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $controlGestion;

    private User $responsible;

    private int $centerId;

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

        $this->responsible = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->responsible->role()->associate($referenteRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => EtapaVigente::Formulacion->value,
            'habilitado' => 1,
        ]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        $this->enableMetasModuleAccessForDependency(1);
        $this->seedLegacyCenCatalogRows();

        $center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-TL',
            'nombre_oficial' => 'Hospital Timeline',
            'nombre_corto' => 'Hospital Timeline',
            'cen_hie_dependency_id' => 1,
        ]);
        $this->centerId = (int) $center->id;

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerId,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $this->centerId,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->responsible->id,
            'assigned_by' => $this->controlGestion->id,
        ]));
    }

    /** @test */
    public function idle_no_consulta_timeline_al_montar_boton(): void
    {
        $this->mock(MetEstablishmentTimelineService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('collectEvents');
        });

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(EstablishmentTimelineComponent::class, [
            'centerId' => $this->centerId,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSet('showModal', false)
            ->assertSee('Ver bitácora');
    }

    /** @test */
    public function pagina_convenio_idle_no_consulta_timeline(): void
    {
        $this->mock(MetEstablishmentTimelineService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('collectEvents');
        });

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerId,
        ])
            ->assertSee('Ver bitácora');
    }

    /** @test */
    public function modal_muestra_envio_reapertura_y_apelacion(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $agreement = MetEstablishmentAgreement::unguarded(fn () => MetEstablishmentAgreement::create([
            'cen_center_id' => $this->centerId,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'estado' => EstadoConvenioEstablecimiento::Enviado,
            'submitted_at' => now()->subHour(),
            'submitted_by' => $this->responsible->id,
            'reopened_at' => now(),
            'reopened_by' => $this->controlGestion->id,
            'reopen_motivo' => 'Corrección de ponderaciones',
        ]));

        Audit::query()->create([
            'user_type' => User::class,
            'user_id' => $this->responsible->id,
            'event' => 'updated',
            'auditable_type' => MetEstablishmentAgreement::class,
            'auditable_id' => $agreement->id,
            'old_values' => ['estado' => EstadoConvenioEstablecimiento::Borrador->value],
            'new_values' => [
                'estado' => EstadoConvenioEstablecimiento::Enviado->value,
                'submitted_at' => now()->subHour()->toDateTimeString(),
                'submitted_by' => $this->responsible->id,
            ],
            'url' => '/',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'tags' => '',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        Audit::query()->create([
            'user_type' => User::class,
            'user_id' => $this->controlGestion->id,
            'event' => 'updated',
            'auditable_type' => MetEstablishmentAgreement::class,
            'auditable_id' => $agreement->id,
            'old_values' => ['estado' => EstadoConvenioEstablecimiento::Enviado->value],
            'new_values' => [
                'estado' => EstadoConvenioEstablecimiento::Borrador->value,
                'reopened_at' => now()->toDateTimeString(),
                'reopened_by' => $this->controlGestion->id,
                'reopen_motivo' => 'Corrección de ponderaciones',
            ],
            'url' => '/',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'tags' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'I-TL-1',
            'nombre' => 'Indicador timeline',
            'meta_valor' => 50,
            'meta_operador' => '>=',
            'estado' => true,
            'orden' => 1,
        ]));

        MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerId,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'appeal_type' => AppealType::CausaExterna->value,
            'motivo' => 'Discrepancia de datos',
            'created_by' => $this->responsible->id,
            'submitted_at' => now()->subMinutes(30),
        ]));

        Livewire::test(EstablishmentTimelineComponent::class, [
            'centerId' => $this->centerId,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openModal')
            ->assertSet('showModal', true)
            ->assertSee('Bitácora del establecimiento')
            ->assertSee('Convenio enviado a SS')
            ->assertSee('Convenio reabierto')
            ->assertSee('Corrección de ponderaciones')
            ->assertSee('Apelación registrada')
            ->assertSee('I-TL-1');
    }

    /** @test */
    public function modal_vacio_sin_eventos(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(EstablishmentTimelineComponent::class, [
            'centerId' => $this->centerId,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openModal')
            ->assertSee('No hay eventos registrados');
    }

    /** @test */
    public function responsable_de_otro_centro_recibe_403(): void
    {
        $otherCenter = $this->createMinimalCenCenter([
            'codigo' => 'CEN-OTHER',
            'nombre_oficial' => 'Otro',
            'nombre_corto' => 'Otro',
            'cen_hie_dependency_id' => 1,
        ]);

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $otherResponsible = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $otherResponsible->role()->associate($referenteRole)->save();

        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $otherCenter->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $otherResponsible->id,
            'assigned_by' => $this->controlGestion->id,
        ]));

        $this->actingAs($otherResponsible)->withSession(['indicator_year' => $this->year]);

        Livewire::test(EstablishmentTimelineComponent::class, [
            'centerId' => $this->centerId,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openModal')
            ->assertForbidden();
    }

    /** @test */
    public function sin_agreement_muestra_apelaciones(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'I-ONLY',
            'nombre' => 'Solo apelación',
            'meta_valor' => 50,
            'meta_operador' => '>=',
            'estado' => true,
            'orden' => 1,
        ]));

        MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerId,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'appeal_type' => AppealType::CausaExterna->value,
            'motivo' => 'Solo apelación',
            'created_by' => $this->responsible->id,
            'submitted_at' => now(),
        ]));

        Livewire::test(EstablishmentTimelineComponent::class, [
            'centerId' => $this->centerId,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openModal')
            ->assertSee('Apelación registrada')
            ->assertSee('I-ONLY')
            ->assertDontSee('Convenio enviado a SS');
    }

    private function enableMetasModuleAccessForDependency(int $depId): void
    {
        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => $depId, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));
    }
}
