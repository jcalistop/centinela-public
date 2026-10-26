<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentAgreement;
use App\Models\MetEstablishmentResponsible;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetEstablishmentAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MetEstablishmentAgreementTest extends TestCase
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
            'codigo' => 'CEN-AG',
            'nombre_oficial' => 'Hospital Agreement',
            'nombre_corto' => 'Hospital Agreement',
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
    public function responsable_guarda_borrador_parcial_sin_suma_100(): void
    {
        $indicators = $this->createIndicators(4);

        foreach (array_slice($indicators, 0, 2) as $indicator) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_center_id' => $this->centerId,
                'cen_hie_dependency_id' => 1,
                'aplica' => true,
                'agrupa_ss' => false,
                'seleccionado' => false,
                'ponderacion' => null,
                'meta_efectiva' => 50,
            ]));
        }

        $this->actingAs($this->responsible)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerId,
        ]);

        foreach (array_slice($indicators, 0, 2) as $indicator) {
            $component
                ->set("configs.{$indicator->id}.seleccionado", true)
                ->set("configs.{$indicator->id}.ponderacion", '40');
        }

        $component->call('saveDraft')->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $indicators[0]->id,
            'cen_center_id' => $this->centerId,
            'seleccionado' => 1,
            'ponderacion' => 40,
        ]);

        $this->assertNull(
            MetEstablishmentAgreement::query()
                ->where('cen_center_id', $this->centerId)
                ->where('indicator_type_id', $this->indicatorType->id)
                ->first()
        );
    }

    /** @test */
    public function enviar_con_suma_incompleta_falla_y_no_marca_enviado(): void
    {
        $indicators = $this->createIndicators(4);

        foreach ($indicators as $i => $indicator) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_center_id' => $this->centerId,
                'cen_hie_dependency_id' => 1,
                'aplica' => true,
                'agrupa_ss' => false,
                'seleccionado' => true,
                'ponderacion' => $i === 3 ? 20 : 25,
                'meta_efectiva' => 50,
            ]));
        }

        $this->actingAs($this->responsible)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerId,
        ])
            ->call('submitToSs')
            ->assertHasErrors('configGeneral');

        $this->assertDatabaseMissing('met_establishment_agreements', [
            'cen_center_id' => $this->centerId,
            'estado' => EstadoConvenioEstablecimiento::Enviado->value,
        ]);
    }

    /** @test */
    public function responsable_envia_convenio_completo_con_firma(): void
    {
        $this->createCompleteConvenio();

        $this->actingAs($this->responsible)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerId,
        ])
            ->call('submitToSs')
            ->assertHasNoErrors()
            ->assertRedirect(route('metas.instrument.establishment.results', [
                'indicatorType' => $this->indicatorType->route,
                'center' => $this->centerId,
            ]));

        $agreement = MetEstablishmentAgreement::query()
            ->where('cen_center_id', $this->centerId)
            ->where('indicator_type_id', $this->indicatorType->id)
            ->first();

        $this->assertNotNull($agreement);
        $this->assertTrue($agreement->isEnviado());
        $this->assertSame($this->responsible->id, (int) $agreement->submitted_by);
        $this->assertNotNull($agreement->submitted_at);
    }

    /** @test */
    public function control_gestion_puede_enviar_convenio(): void
    {
        $this->createCompleteConvenio();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerId,
        ])
            ->call('submitToSs')
            ->assertHasNoErrors();

        $agreement = MetEstablishmentAgreement::query()
            ->where('cen_center_id', $this->centerId)
            ->first();

        $this->assertSame($this->controlGestion->id, (int) $agreement->submitted_by);
    }

    /** @test */
    public function control_gestion_reabre_convenio_enviado(): void
    {
        $this->createCompleteConvenio();

        MetEstablishmentAgreement::unguarded(fn () => MetEstablishmentAgreement::create([
            'cen_center_id' => $this->centerId,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'estado' => EstadoConvenioEstablecimiento::Enviado,
            'submitted_at' => now(),
            'submitted_by' => $this->responsible->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->assertFalse(
            app(MetEstablishmentAgreementService::class)->isEditable(
                $this->centerId,
                $this->year->id,
                $this->indicatorType->id,
            )
        );

        app(MetEstablishmentAgreementService::class)->reopen(
            $this->centerId,
            $this->year->id,
            $this->indicatorType->id,
            1,
            $this->controlGestion,
            'Corrección de ponderaciones',
        );

        $agreement = MetEstablishmentAgreement::query()->where('cen_center_id', $this->centerId)->first();
        $this->assertSame(EstadoConvenioEstablecimiento::Borrador, $agreement->estado);
        $this->assertSame($this->controlGestion->id, (int) $agreement->reopened_by);
        $this->assertSame('Corrección de ponderaciones', $agreement->reopen_motivo);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerId,
        ])
            ->assertSee('Guardar borrador');
    }

    /** @test */
    public function no_reabre_fuera_de_formulacion(): void
    {
        MetEstablishmentAgreement::unguarded(fn () => MetEstablishmentAgreement::create([
            'cen_center_id' => $this->centerId,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'estado' => EstadoConvenioEstablecimiento::Enviado,
            'submitted_at' => now(),
            'submitted_by' => $this->responsible->id,
        ]));

        IndicatorTypeSetting::query()
            ->where('indicator_type_id', $this->indicatorType->id)
            ->where('year_id', $this->year->id)
            ->update(['etapa_vigente' => EtapaVigente::Autoevaluacion->value]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->expectException(ValidationException::class);

        app(MetEstablishmentAgreementService::class)->reopen(
            $this->centerId,
            $this->year->id,
            $this->indicatorType->id,
            1,
            $this->controlGestion,
            'Intento fuera de formulación',
        );
    }

    /** @test */
    public function responsable_no_puede_reabrir(): void
    {
        MetEstablishmentAgreement::unguarded(fn () => MetEstablishmentAgreement::create([
            'cen_center_id' => $this->centerId,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'estado' => EstadoConvenioEstablecimiento::Enviado,
            'submitted_at' => now(),
            'submitted_by' => $this->responsible->id,
        ]));

        $this->expectException(HttpException::class);

        app(MetEstablishmentAgreementService::class)->reopen(
            $this->centerId,
            $this->year->id,
            $this->indicatorType->id,
            1,
            $this->responsible,
            'Quiero corregir',
        );
    }

    /** @test */
    public function guardar_borrador_con_suma_100_no_marca_enviado(): void
    {
        $this->createCompleteConvenio();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerId,
        ])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertNull(
            app(MetEstablishmentAgreementService::class)->find(
                $this->centerId,
                $this->year->id,
                $this->indicatorType->id,
            )
        );
    }

    /**
     * @return list<MetIndicator>
     */
    private function createIndicators(int $count): array
    {
        $indicators = [];

        for ($i = 1; $i <= $count; $i++) {
            $indicators[] = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'clave' => "I{$i}",
                'nombre' => "Indicador {$i}",
                'orden' => $i,
                'estado' => true,
                'decimales' => 2,
                'decimales_modo' => 'redondear',
                'meta_valor' => 50.0,
                'meta_operador' => '>=',
            ]));
        }

        return $indicators;
    }

    /**
     * @return list<MetIndicator>
     */
    private function createCompleteConvenio(): array
    {
        $indicators = $this->createIndicators(4);

        foreach ($indicators as $indicator) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_center_id' => $this->centerId,
                'cen_hie_dependency_id' => 1,
                'aplica' => true,
                'agrupa_ss' => false,
                'seleccionado' => true,
                'ponderacion' => 25,
                'meta_efectiva' => 50,
            ]));
        }

        return $indicators;
    }

    private function enableMetasModuleAccessForDependency(int $depId): void
    {
        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => $depId, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));
    }
}
