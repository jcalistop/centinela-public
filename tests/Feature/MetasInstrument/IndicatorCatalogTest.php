<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Metas\DataFieldSource;
use App\Enums\Metas\UnidadMedida;
use App\Livewire\MetasInstrument\IndicatorCatalogComponent;
use App\Livewire\MetasInstrument\IndicatorCatalogFormComponent;
use App\Livewire\MetasInstrument\IndicatorCatalogShowComponent;
use App\Mail\ReferenteAssignedMail;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorAmbito;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class IndicatorCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $parametrizador;

    private User $adminUser;

    private User $controlGestion;

    private User $visualizador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        // FK requerida: cen_hie_dependency_id = 1
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $parametrizadorRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->parametrizador = User::factory()->create(['role_id' => $parametrizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $this->parametrizador->role()->associate($parametrizadorRole)->save();

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->visualizador = User::factory()->create(['role_id' => $visualizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $this->visualizador->role()->associate($visualizadorRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->enableMetasModuleAccessForDependency(1);
    }

    private function enableMetasModuleAccessForDependency(int $depId): void
    {
        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => $depId, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $depId,
            ],
            [
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => true,
            ],
        ));
    }

    /** @test */
    public function parametrizador_can_render_catalog_component(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertStatus(200);
    }

    /** @test */
    public function admin_can_render_catalog_component(): void
    {
        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertStatus(200);
    }

    /** @test */
    public function visualizador_can_render_catalog_component(): void
    {
        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertStatus(200);
    }

    /** @test */
    public function control_gestion_can_access_catalog(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertStatus(200);
    }

    /** @test */
    public function catalog_hides_ambito_badges_for_parametrizador_when_assigned(): void
    {
        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'AMB-CAT',
                'nombre' => 'Indicador catálogo ámbitos',
            ])
        ));

        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, [$ambito->id]);

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $html = Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Indicador catálogo ámbitos', false)
            ->assertDontSee('metas-catalog-ambito-alert', false)
            ->html();

        $this->assertStringNotContainsString('label label-info', $html);
        $this->assertStringNotContainsString('/admin/dashboard/ambitos/'.$ambito->slug, $html);
    }

    /** @test */
    public function catalog_shows_ambito_empty_alert_for_parametrizador(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'AMB-EMPTY',
                'nombre' => 'Indicador sin ámbito catálogo',
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Indicador sin ámbito catálogo', false)
            ->assertSee('metas-catalog-alerts', false)
            ->assertSee('metas-catalog-ambito-alert', false)
            ->assertSee('Sin ámbito asignado', false);
    }

    /** @test */
    public function catalog_hides_ambito_empty_alert_for_control_gestion(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'AMB-CG-HIDE',
                'nombre' => 'Indicador sin alerta ámbito CG',
            ])
        ));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Indicador sin alerta ámbito CG', false)
            ->assertDontSee('metas-catalog-ambito-alert', false);
    }

    /** @test */
    public function catalog_hides_ambito_labels_under_name_for_control_gestion(): void
    {
        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'AMB-HIDE',
                'nombre' => 'Indicador sin etiquetas ámbito',
            ])
        ));

        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, [$ambito->id]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $html = Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Indicador sin etiquetas ámbito', false)
            ->html();

        $this->assertStringNotContainsString(
            '<span class="label label-info" style="font-weight:normal; margin-right:2px;">'.$ambito->nombre.'</span>',
            $html,
        );
    }

    /** @test */
    public function catalog_shows_referentes_empty_alert_for_control_gestion(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'REF-EMPTY',
                'nombre' => 'Indicador sin referentes catálogo',
            ])
        ));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->assertSee('Indicador sin referentes catálogo', false)
            ->assertSee('metas-catalog-alerts', false)
            ->assertSee('metas-catalog-referentes-alert', false)
            ->assertSee('Sin referentes asignados', false);
    }

    /** @test */
    public function visualizador_catalog_hides_referentes_and_empty_alert(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'name' => 'Referente Enlace',
        ]);
        $referente->role()->associate($referenteRole)->save();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'REF-LINK',
                'nombre' => 'Indicador con enlace referente',
            ])
        ));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $referente->id,
        ]));

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->assertSee('Indicador con enlace referente', false)
            ->assertDontSee('Referente Enlace')
            ->assertDontSee(route('voyager.users.show', $referente->id), false)
            ->assertDontSee('metas-catalog-referentes-alert', false);
    }

    /** @test */
    public function show_ficha_renders_referentes_modal_after_open(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'REF-MODAL',
                'nombre' => 'Modal referentes ficha',
            ])
        ));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->assertSet('showReferentesModal', false)
            ->assertDontSee('centinela-metas-modal-referentes', false)
            ->call('openReferentes')
            ->assertSet('showReferentesModal', true)
            ->assertSee('centinela-metas-modal-referentes', false)
            ->assertSee('Buscar referente', false)
            ->assertSee('Gestionar referentes', false);
    }

    /** @test */
    public function show_ficha_shows_ambito_empty_alert_for_parametrizador(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'AMB-FICHA-EMPTY',
                'nombre' => 'Ficha sin ámbitos',
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->assertSee('metas-ficha-empty-alert', false)
            ->assertSee('Sin ámbito asignado.', false)
            ->assertSeeHtml('alert alert-warning');
    }

    /** @test */
    public function show_ficha_shows_referentes_empty_alert_for_control_gestion(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'REF-FICHA-EMPTY',
                'nombre' => 'Ficha sin referentes',
            ])
        ));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->assertSee('metas-ficha-empty-alert', false)
            ->assertSee('Sin referentes asignados.', false)
            ->assertSeeHtml('alert alert-warning');
    }

    /** @test */
    public function show_ficha_displays_assigned_ambito_labels_below_objective(): void
    {
        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'AMB-FICHA',
                'nombre' => 'Indicador con ámbitos en ficha',
            ])
        ));

        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, [$ambito->id]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->assertSee('Objetivo y/o descripción del indicador', false)
            ->assertSee('Ámbitos', false)
            ->assertSee($ambito->nombre, false)
            ->assertSeeHtml('/admin/dashboard/ambitos/'.$ambito->slug)
            ->assertSee('Referentes', false);
    }

    /** @test */
    public function show_ficha_ambito_labels_are_plain_when_non_admin_dashboard_disabled(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', false);

        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'AMB-NODASH',
                'nombre' => 'Indicador sin enlace ámbito',
            ])
        ));

        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, [$ambito->id]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $html = Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->assertSee($ambito->nombre, false)
            ->html();

        $this->assertStringNotContainsString('/admin/dashboard/ambitos/', $html);
    }

    /** @test */
    public function show_ficha_muestra_tipos_efectivos_ss_y_leyenda(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'FICHA-TIPOS',
                'nombre' => 'Indicador tipos ficha',
            ])
        ));

        $fieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'semestral',
            'aggregation_type' => 'ultimo_dato',
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->assertSee('Semestral', false)
            ->assertSee('Último dato', false)
            ->assertSee('metas-ficha-legend', false)
            ->assertSee('Tipo de entrada:', false)
            ->assertSee('Sumatoria:', false)
            ->assertSee('configuración realizada por Control de Gestión', false)
            ->assertDontSee('>Leyenda<', false);
    }

    /** @test */
    public function open_referentes_add_and_remove_mutate_referentes_list(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'name' => 'Referente Lista',
        ]);
        $referente->role()->associate($referenteRole)->save();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'REF-LIST',
                'nombre' => 'Indicador lista referentes',
            ])
        ));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->call('openReferentes')
            ->assertSet('referentesList', [])
            ->call('addReferente', $referente->id)
            ->assertSet('referentesList', [$referente->id])
            ->call('removeReferente', $referente->id)
            ->assertSet('referentesList', []);
    }

    /** @test */
    public function referente_search_filters_available_referentes_in_modal(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $alpha = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'name' => 'Ana Alpha',
        ]);
        $alpha->role()->associate($referenteRole)->save();

        $beta = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'name' => 'Bruno Beta',
        ]);
        $beta->role()->associate($referenteRole)->save();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'REF-SEARCH',
                'nombre' => 'Indicador búsqueda referentes',
            ])
        ));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->call('openReferentes')
            ->set('referenteSearch', 'Ana')
            ->assertSee('Ana Alpha')
            ->assertDontSee('Bruno Beta');
    }

    /** @test */
    public function control_gestion_save_referentes_closes_modal_and_persists(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($referenteRole)->save();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'REF-01',
            'nombre' => 'Indicador referentes',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->call('openReferentes')
            ->assertSet('showReferentesModal', true)
            ->call('addReferente', $referente->id)
            ->call('saveReferentes')
            ->assertSet('showReferentesModal', false);

        $this->assertDatabaseHas('met_indicator_referentes', [
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $referente->id,
        ]);
    }

    /** @test */
    public function control_gestion_notify_referentes_queues_mail_only_for_new_assignments(): void
    {
        Mail::fake();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $existing = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'existente@example.com',
        ]);
        $existing->role()->associate($referenteRole)->save();

        $newReferente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'nuevo@example.com',
        ]);
        $newReferente->role()->associate($referenteRole)->save();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'REF-NOTIFY',
            'nombre' => 'Indicador notificación',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $existing->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->call('openReferentes')
            ->call('addReferente', $newReferente->id)
            ->set('notifyReferentes', true)
            ->call('saveReferentes');

        Mail::assertQueued(ReferenteAssignedMail::class, 1);
        Mail::assertQueued(ReferenteAssignedMail::class, function (ReferenteAssignedMail $mail) use ($newReferente): bool {
            return $mail->hasTo($newReferente->email);
        });
    }

    /** @test */
    public function control_gestion_save_referentes_without_notify_does_not_queue_mail(): void
    {
        Mail::fake();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente@example.com',
        ]);
        $referente->role()->associate($referenteRole)->save();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'REF-NOMAIL',
            'nombre' => 'Indicador sin correo',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->call('openReferentes')
            ->assertSet('notifyReferentes', false)
            ->call('addReferente', $referente->id)
            ->call('saveReferentes');

        Mail::assertNothingQueued();
    }

    /** @test */
    public function control_gestion_notify_without_new_referentes_does_not_queue_mail(): void
    {
        Mail::fake();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente@example.com',
        ]);
        $referente->role()->associate($referenteRole)->save();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'REF-SAME',
            'nombre' => 'Indicador sin cambios',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $referente->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->call('openReferentes')
            ->set('notifyReferentes', true)
            ->call('saveReferentes');

        Mail::assertNothingQueued();
    }

    /** @test */
    public function parametrizador_can_create_indicator(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->catalogFormTest()
            ->set('clave', 'MET-001')
            ->set('nombre', 'Indicador de prueba')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicators', [
            'clave' => 'MET-001',
            'nombre' => 'Indicador de prueba',
            'year_id' => $this->year->id,
            'orden' => 1,
            'umbral_inferior' => '0.00',
            'umbral_superior' => '100.00',
            'meta_valor' => '85.50',
            'meta_operador' => '>=',
            'unidad_medida' => '%',
            'nivel_aplicacion' => 'Ámbito de prueba',
        ]);
    }

    /** @test */
    public function save_fails_with_duplicate_clave(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'MET-001',
                'nombre' => 'Indicador existente',
            ])
        ));

        $this->catalogFormTest()
            ->set('clave', 'MET-001')
            ->set('nombre', 'Otro indicador')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->call('save')
            ->assertHasErrors(['clave']);
    }

    /** @test */
    public function parametrizador_can_edit_indicator(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'MET-002',
                'nombre' => 'Indicador original',
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->catalogFormTest($indicator)
            ->assertSet('indicatorId', $indicator->id)
            ->assertSet('clave', 'MET-002')
            ->set('nombre', 'Indicador editado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicators', [
            'id' => $indicator->id,
            'nombre' => 'Indicador editado',
        ]);
    }

    /** @test */
    public function parametrizador_can_delete_indicator(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-003',
            'nombre' => 'Para eliminar',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->delete(route('metas.instrument.indicators.destroy', [$this->indicatorType, $indicator]))
            ->assertRedirect(route('metas.instrument.index', $this->indicatorType))
            ->assertSessionHas('message', 'Indicador eliminado.')
            ->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseMissing('met_indicators', ['id' => $indicator->id]);
    }

    /** @test */
    public function parametrizador_cannot_delete_indicator_with_loaded_data_in_any_dependency(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'MET-LOADED',
                'nombre' => 'Con datos cargados',
            ])
        ));

        $field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $otherDependency = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 99,
            'codigo' => 'SS99',
            'nombre' => 'Otro SS',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-OTRO',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => $otherDependency->id,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Otro SS',
            'nombre_corto' => 'HospOtro',
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

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => $otherDependency->id,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10,
        ]));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->delete(route('metas.instrument.indicators.destroy', [$this->indicatorType, $indicator]))
            ->assertRedirect(route('metas.instrument.indicators.show', [$this->indicatorType, $indicator]))
            ->assertSessionHas('alert-type', 'error');

        $this->assertDatabaseHas('met_indicators', ['id' => $indicator->id]);
    }

    /** @test */
    public function show_page_displays_management_actions_for_parametrizador(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'SHOW-ACT',
                'nombre' => 'Acciones ficha',
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $indicator]))
            ->assertOk()
            ->assertSee('Volver al catálogo de indicadores')
            ->assertSee('Editar')
            ->assertSee('Eliminar')
            ->assertDontSee('id="tab-aplicables-convenidos"', false)
            ->assertDontSee('id="tab-desempeno"', false);
    }

    /** @test */
    public function catalog_shows_promedio_cumplimiento_column_for_operational_roles(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'CAT-CUMPL',
                'nombre' => 'Indicador cumplimiento catálogo',
                'meta_valor' => 100,
            ])
        ));

        $this->createCenterCatalogDependencies();

        $center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-CUMPL',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Cumplimiento',
            'nombre_corto' => 'HospCumpl',
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

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $center->id,
            'aplica' => true,
            'seleccionado' => false,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'numerador' => 80,
            'denominador' => 100,
            'resultado' => 80,
            'cumple_meta' => true,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.index', $this->indicatorType))
            ->assertOk()
            ->assertSee('Cumplimiento promedio', false)
            ->assertSee('80.00%', false);
    }

    /** @test */
    public function catalog_uses_batch_desempeno_and_skips_it_when_duplicate_modal_open(): void
    {
        $first = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'CAT-BATCH-1',
                'nombre' => 'Indicador batch 1',
                'meta_valor' => 100,
            ])
        ));

        $second = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'CAT-BATCH-2',
                'nombre' => 'Indicador batch 2',
                'meta_valor' => 100,
            ])
        ));

        $batchPayload = [
            (int) $first->id => [
                'promedio_cumplimiento' => 80.0,
                'rows' => [],
                'indicador_nombre' => 'Indicador batch 1',
                'umbral_superior' => null,
                'uses_complex_primary_components' => false,
            ],
            (int) $second->id => [
                'promedio_cumplimiento' => 50.0,
                'rows' => [],
                'indicador_nombre' => 'Indicador batch 2',
                'umbral_superior' => null,
                'uses_complex_primary_components' => false,
            ],
        ];

        $mock = Mockery::mock(MetIndicatorDesempenoByEstablishmentService::class);
        // Catálogo: un solo buildBatch (cumplimiento + errores agregados reutilizan payload — SDD-169).
        $mock->shouldReceive('buildBatchForIndicators')
            ->once()
            ->andReturn($batchPayload);
        $mock->shouldReceive('buildForIndicator')->andReturn([
            'promedio_cumplimiento' => null,
            'rows' => [],
            'indicador_nombre' => '',
            'umbral_superior' => null,
            'uses_complex_primary_components' => false,
        ]);
        $this->app->instance(MetIndicatorDesempenoByEstablishmentService::class, $mock);

        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('80.00%', false)
            ->assertSee('50.00%', false)
            ->call('openDuplicateModal', $first->id)
            ->assertSet('showDuplicateModal', true)
            ->assertDontSee('80.00%', false)
            ->assertDontSee('50.00%', false);
    }

    /** @test */
    public function catalog_read_actions_only_show_ficha_button(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'CAT-READ',
                'nombre' => 'Solo ficha en catálogo',
            ])
        ));

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        $response = $this->get(route('metas.instrument.index', $this->indicatorType));

        $response->assertOk()
            ->assertSee('Ver indicador', false)
            ->assertDontSee('Cargar datos', false)
            ->assertDontSee('openReferentes', false)
            ->assertDontSee('openEstablecimientosModal', false)
            ->assertDontSee('#tab-desempeno', false)
            ->assertDontSee('centinela-metas-modal-establecimientos', false);
    }

    /** @test */
    public function catalog_hides_cargar_datos_button_for_authorized_roles(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'CAT-NO-CARGA',
                'nombre' => 'Sin botón carga en catálogo',
            ])
        ));

        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.index', $this->indicatorType))
            ->assertOk()
            ->assertSee('Ver indicador', false)
            ->assertDontSee('Cargar datos', false);
    }

    /** @test */
    public function show_page_hides_edit_for_visualizador_but_shows_desempeno(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'SHOW-VIS',
                'nombre' => 'Ficha visualizador acciones',
            ])
        ));

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        $response = $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $indicator]));

        $response->assertOk()
            ->assertSee('Establecimientos', false)
            ->assertSee('id="tab-aplicables-convenidos"', false)
            ->assertSee('Desempeño', false)
            ->assertSee('id="tab-desempeno"', false)
            ->assertDontSee('>Editar<', false);

        $this->assertStringNotContainsString('indicators.destroy', $response->getContent());
    }

    /** @test */
    public function catalog_list_does_not_show_edit_or_delete_buttons(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'LIST-NO-ED',
                'nombre' => 'Sin editar en lista',
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $response = $this->get(route('metas.instrument.index', $this->indicatorType));

        $response->assertOk()
            ->assertSee($indicator->nombre);

        $this->assertStringNotContainsString(
            route('metas.instrument.indicators.edit', [$this->indicatorType, $indicator]),
            $response->getContent()
        );
        $this->assertStringNotContainsString(
            route('metas.instrument.indicators.destroy', [$this->indicatorType, $indicator]),
            $response->getContent()
        );
    }

    /** @test */
    public function parametrizador_save_redirects_to_show_after_create(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->catalogFormTest()
            ->set('clave', 'MET-REDIR')
            ->set('nombre', 'Redirect a ficha')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('metas.instrument.indicators.show', [
                $this->indicatorType,
                MetIndicator::query()->where('clave', 'MET-REDIR')->value('id'),
            ]));
    }

    /** @test */
    public function save_requires_meta_valor_operador_unidad_and_nivel_aplicacion(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-REQ')
            ->set('nombre', 'Indicador incompleto')
            ->set('metaValor', '')
            ->set('metaOperador', '')
            ->set('unidadMedida', '')
            ->set('nivelAplicacion', '')
            ->call('save')
            ->assertHasErrors(['metaValor', 'metaOperador', 'unidadMedida', 'nivelAplicacion']);
    }

    /** @test */
    public function parametrizador_can_save_exclusiones_and_rounds_meta_valor_to_two_decimals(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-EXC')
            ->set('nombre', 'Indicador exclusiones')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('metaValor', '12.3456')
            ->set('exclusiones', 'Hospitales sin urgencia')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicators', [
            'clave' => 'MET-EXC',
            'meta_valor' => '12.35',
            'exclusiones' => 'Hospitales sin urgencia',
        ]);
    }

    /** @test */
    public function save_requires_clave_and_nombre(): void
    {
        $this->catalogFormTest()
            ->set('clave', '')
            ->set('nombre', '')
            ->call('save')
            ->assertHasErrors(['clave', 'nombre']);
    }

    /** @test */
    public function decimales_modo_defaults_to_redondear(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-DEC')
            ->set('nombre', 'Indicador decimales')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicators', [
            'clave' => 'MET-DEC',
            'decimales_modo' => 'redondear',
        ]);
    }

    /** @test */
    public function decimales_modo_can_be_set_to_truncar(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-TRC')
            ->set('nombre', 'Indicador truncar')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('decimalesModo', 'truncar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicators', [
            'clave' => 'MET-TRC',
            'decimales_modo' => 'truncar',
        ]);
    }

    /** @test */
    public function decimales_modo_rejects_invalid_value(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-INV')
            ->set('nombre', 'Indicador inválido')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('decimalesModo', 'aproximar')
            ->call('save')
            ->assertHasErrors(['decimalesModo']);
    }

    /** @test */
    public function decimales_modo_loads_correctly_on_edit(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'MET-EDIT',
                'nombre' => 'Para editar modo',
                'decimales' => 3,
                'decimales_modo' => 'truncar',
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->catalogFormTest($indicator)
            ->assertSet('decimalesModo', 'truncar')
            ->assertSet('decimales', 3);
    }

    /** @test */
    public function save_rejects_thresholds_outside_0_to_100_range(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-UMB-001')
            ->set('nombre', 'Indicador umbrales')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('umbralInferior', -1)
            ->set('umbralSuperior', 101)
            ->call('save')
            ->assertHasErrors(['umbralInferior', 'umbralSuperior']);
    }

    /** @test */
    public function save_rejects_when_lower_threshold_exceeds_upper(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-UMB-002')
            ->set('nombre', 'Indicador umbrales inválidos')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('umbralInferior', 71)
            ->set('umbralSuperior', 70)
            ->call('save')
            ->assertHasErrors(['umbralInferior', 'umbralSuperior']);
    }

    /** @test */
    public function save_accepts_equal_thresholds(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-UMB-003')
            ->set('nombre', 'Indicador umbral único')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('umbralInferior', 95)
            ->set('umbralSuperior', 95)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicators', [
            'clave' => 'MET-UMB-003',
            'umbral_inferior' => 95,
            'umbral_superior' => 95,
        ]);
    }

    /** @test */
    public function parametrizador_can_create_indicator_with_fields_and_formula_in_one_step(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-FLD-001')
            ->set('nombre', 'Indicador con maqueta')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('newFieldLabel', 'Numerador A')
            ->set('newFieldComponente', 'numerador')
            ->set('newFieldInputType', 'mensual')
            ->set('newFieldAggregationType', 'sumatoria')
            ->set('newFieldFuente', DataFieldSource::Sigte->value)
            ->call('addField')
            ->set('newFieldLabel', 'Denominador B')
            ->set('newFieldComponente', 'denominador')
            ->set('newFieldInputType', 'mensual')
            ->set('newFieldAggregationType', 'sumatoria')
            ->set('newFieldFuente', DataFieldSource::RemSa->value)
            ->call('addField')
            ->set('formulaJson', json_encode([
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'paren', 'value' => ')'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ]))
            ->call('save')
            ->assertHasNoErrors();

        $indicator = MetIndicator::where('clave', 'MET-FLD-001')->firstOrFail();

        $this->assertDatabaseHas('met_data_fields', [
            'met_indicator_id' => $indicator->id,
            'label' => 'Numerador A',
            'variable_key' => 'A',
            'fuente' => DataFieldSource::Sigte->value,
        ]);

        $this->assertDatabaseHas('met_data_fields', [
            'met_indicator_id' => $indicator->id,
            'label' => 'Denominador B',
            'variable_key' => 'B',
            'fuente' => DataFieldSource::RemSa->value,
        ]);

        $this->assertDatabaseHas('met_formulas', [
            'met_indicator_id' => $indicator->id,
            'description' => '( A / B ) * 100',
        ]);
    }

    /** @test */
    public function existing_fields_keep_null_fuente_after_migration(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-FUENTE-NULL',
            'nombre' => 'Campo sin fuente',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        $field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo legacy',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $this->assertNull($field->fresh()->fuente);
    }

    /** @test */
    public function edit_page_renders_saved_formula_tokens_for_builder(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-FORM-EDIT',
            'nombre' => 'Indicador con fórmula',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        $expression = [
            ['type' => 'paren', 'value' => '('],
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'paren', 'value' => ')'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ];

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $indicator->id,
            'expression' => $expression,
            'description' => '( A / B ) * 100',
        ]));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->catalogFormTest($indicator)
            ->assertSet('formulaJson', json_encode($expression))
            ->assertSet('formulaDescription', '( A / B ) * 100')
            ->assertSee('Constructor de fórmula')
            ->assertSeeHtml('formulaBuilder');
    }

    /** @test */
    public function removing_field_clears_formula_state(): void
    {
        $this->catalogFormTest()
            ->set('newFieldLabel', 'Numerador A')
            ->call('addField')
            ->set('newFieldLabel', 'Denominador B')
            ->call('addField')
            ->set('formulaJson', json_encode([
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
            ]))
            ->call('removeField', 0)
            ->assertCount('fields', 1)
            ->assertSet('formulaJson', '[]')
            ->assertSet('formulaDescription', '')
            ->assertSet('formulaRevision', 1);
    }

    /** @test */
    public function parametrizador_can_edit_indicator_fields_and_formula(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'MET-EDIT-FLD',
                'nombre' => 'Indicador maqueta',
            ])
        ));

        $fieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'fuente' => DataFieldSource::Sigte->value,
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'fuente' => DataFieldSource::RemSa->value,
            'orden' => 1,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
            ],
            'description' => 'A / B',
        ]));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->catalogFormTest($indicator)
            ->set('fields.0.label', 'Numerador actualizado')
            ->set('fields.0.fuente', DataFieldSource::Grd->value)
            ->set('formulaJson', json_encode([
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'paren', 'value' => ')'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ]))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_data_fields', [
            'id' => $fieldA->id,
            'label' => 'Numerador actualizado',
            'fuente' => DataFieldSource::Grd->value,
        ]);

        $this->assertDatabaseHas('met_formulas', [
            'met_indicator_id' => $indicator->id,
            'description' => '( A / B ) * 100',
        ]);
    }

    /** @test */
    public function guarda_indicador_con_formula_min(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-MIN-001')
            ->set('nombre', 'Indicador con min')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('newFieldLabel', 'Numerador A')
            ->call('addField')
            ->set('formulaJson', json_encode([
                [
                    'type' => 'fn',
                    'name' => 'min',
                    'args' => [
                        [['type' => 'var', 'key' => 'A']],
                        [['type' => 'lit', 'value' => '1']],
                    ],
                ],
            ]))
            ->call('save')
            ->assertHasNoErrors();

        $indicator = MetIndicator::where('clave', 'MET-MIN-001')->firstOrFail();

        $this->assertDatabaseHas('met_formulas', [
            'met_indicator_id' => $indicator->id,
            'description' => 'min(A, 1)',
        ]);
    }

    /** @test */
    public function save_rechaza_formula_min_con_argumento_vacio(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-MIN-INV')
            ->set('nombre', 'Indicador min inválido')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('newFieldLabel', 'Numerador A')
            ->call('addField')
            ->set('formulaJson', json_encode([
                [
                    'type' => 'fn',
                    'name' => 'min',
                    'args' => [
                        [],
                        [['type' => 'lit', 'value' => '1']],
                    ],
                ],
            ]))
            ->call('save')
            ->assertHasErrors(['formulaJson'])
            ->assertSee('alert alert-danger', false)
            ->assertSee('El argumento 1 de min() no puede estar vacío.', false)
            ->assertSee('Variables y fórmula de cálculo', false);
    }

    /** @test */
    public function save_rechaza_formula_division_por_cero_muestra_alerta_en_seccion_variables(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-DIV-0')
            ->set('nombre', 'Indicador división cero')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('newFieldLabel', 'Numerador A')
            ->call('addField')
            ->set('formulaJson', json_encode([
                ['type' => 'lit', 'value' => '1'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'lit', 'value' => '0'],
            ]))
            ->call('save')
            ->assertHasErrors(['formulaJson'])
            ->assertSee('alert alert-danger', false)
            ->assertSee('La fórmula contiene una división por cero inválida.', false)
            ->assertSee('Variables y fórmula de cálculo', false);
    }

    /** @test */
    public function save_rejects_invalid_fuente_on_existing_field(): void
    {
        $this->catalogFormTest()
            ->set('clave', 'MET-FUENTE-INV')
            ->set('nombre', 'Indicador fuente inválida')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->set('newFieldLabel', 'Campo A')
            ->call('addField')
            ->set('fields.0.fuente', 'fuente_invalida')
            ->call('save')
            ->assertHasErrors(['fields.0.fuente']);
    }

    /** @test */
    public function cannot_remove_field_with_monthly_data(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-NO-DEL',
            'nombre' => 'Indicador con carga',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        $field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo con datos',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $this->createCenterCatalogDependencies();

        $center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-CAT-001',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Catálogo',
            'nombre_corto' => 'HospCat',
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

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10,
        ]));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->catalogFormTest($indicator)
            ->call('removeField', 0)
            ->assertHasErrors(['fields'])
            ->assertCount('fields', 1);
    }

    /** @test */
    public function deleting_indicator_cascades_fields_and_formula(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-CASCADE',
            'nombre' => 'Indicador cascada',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        $field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $indicator->id,
            'expression' => [['type' => 'var', 'key' => 'A']],
            'description' => 'A',
        ]));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->delete(route('metas.instrument.indicators.destroy', [$this->indicatorType, $indicator]))
            ->assertRedirect(route('metas.instrument.index', $this->indicatorType));

        $this->assertDatabaseMissing('met_indicators', ['id' => $indicator->id]);
        $this->assertDatabaseMissing('met_data_fields', ['id' => $field->id]);
        $this->assertDatabaseMissing('met_formulas', ['met_indicator_id' => $indicator->id]);
    }

    /** @test */
    public function catalog_browse_paginates_and_limits_operational_batch_to_page(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            MetIndicator::unguarded(fn () => MetIndicator::create(
                $this->minimalIndicatorAttributes([
                    'clave' => sprintf('PAGE-%02d', $i),
                    'nombre' => "Indicador página {$i}",
                    'orden' => $i,
                    'meta_valor' => 100,
                ])
            ));
        }

        $pageIds = MetIndicator::query()
            ->where('indicator_type_id', $this->indicatorType->id)
            ->where('year_id', $this->year->id)
            ->orderBy('orden')
            ->orderBy('clave')
            ->limit(20)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $batchPayload = [];
        foreach ($pageIds as $id) {
            $batchPayload[$id] = [
                'promedio_cumplimiento' => 70.0,
                'rows' => [],
                'indicador_nombre' => '',
                'umbral_superior' => null,
                'uses_complex_primary_components' => false,
            ];
        }

        $mock = Mockery::mock(MetIndicatorDesempenoByEstablishmentService::class);
        $mock->shouldReceive('buildBatchForIndicators')
            ->once()
            ->withArgs(function (Collection $indicators) use ($pageIds): bool {
                return $indicators->count() === 20
                    && $indicators->pluck('id')->map(fn ($id): int => (int) $id)->all() === $pageIds;
            })
            ->andReturn($batchPayload);
        $mock->shouldReceive('buildForIndicator')->andReturn([
            'promedio_cumplimiento' => null,
            'rows' => [],
            'indicador_nombre' => '',
            'umbral_superior' => null,
            'uses_complex_primary_components' => false,
        ]);
        $this->app->instance(MetIndicatorDesempenoByEstablishmentService::class, $mock);

        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('PAGE-01')
            ->assertSee('PAGE-20')
            ->assertDontSee('PAGE-21')
            ->assertSeeHtml('wire:click="gotoPage(2');
    }

    /** @test */
    public function catalog_search_and_ambito_filter_reset_to_first_page(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            MetIndicator::unguarded(fn () => MetIndicator::create(
                $this->minimalIndicatorAttributes([
                    'clave' => sprintf('RST-%02d', $i),
                    'nombre' => "Reset page {$i}",
                    'orden' => $i,
                ])
            ));
        }

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->call('gotoPage', 2)
            ->assertSee('RST-21')
            ->set('search', 'RST-01')
            ->assertSet('paginators.page', 1)
            ->assertSee('RST-01')
            ->assertDontSee('RST-21')
            ->call('gotoPage', 2)
            ->set('ambitoFilter', 'none')
            ->assertSet('paginators.page', 1);
    }

    /** @test */
    public function sort_mode_loads_full_catalog_without_pagination_links(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            MetIndicator::unguarded(fn () => MetIndicator::create(
                $this->minimalIndicatorAttributes([
                    'clave' => sprintf('SORT-%02d', $i),
                    'nombre' => "Sort {$i}",
                    'orden' => $i,
                ])
            ));
        }

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->call('enterSortMode')
            ->assertSet('sortMode', true)
            ->assertSee('SORT-01')
            ->assertSee('SORT-21')
            ->assertDontSeeHtml('wire:click="gotoPage');
    }

    /** @test */
    public function catalog_empty_filter_shows_empty_message_without_error(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'EMPTY-FILTER',
                'nombre' => 'Con resultados',
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->set('search', 'sin-coincidencias-xyz')
            ->assertSee('No hay indicadores registrados para el año activo.')
            ->assertStatus(200);
    }

    /** @test */
    public function visualizador_cannot_enter_sort_mode(): void
    {
        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->call('enterSortMode')
            ->assertForbidden();
    }

    /** @test */
    public function parametrizador_can_enter_and_exit_sort_mode(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->call('enterSortMode')
            ->assertSet('sortMode', true)
            ->call('exitSortMode')
            ->assertSet('sortMode', false);
    }

    /** @test */
    public function parametrizador_can_reorder_indicators(): void
    {
        $first = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'ORD-A',
            'nombre' => 'Primero',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        $second = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'ORD-B',
            'nombre' => 'Segundo',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 2,
            'estado' => true,
        ]));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->call('updateIndicatorOrder', [$second->id, $first->id])
            ->assertHasNoErrors();

        $this->assertSame(2, $first->fresh()->orden);
        $this->assertSame(1, $second->fresh()->orden);
    }

    /** @test */
    public function create_indicator_assigns_next_orden_when_catalog_has_existing(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'EXIST',
            'nombre' => 'Existente',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 7,
            'estado' => true,
        ]));

        $this->catalogFormTest()
            ->set('clave', 'NEW-ORD')
            ->set('nombre', 'Nuevo orden')
            ->tap(fn ($c) => $this->fillRequiredCatalogForm($c))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicators', [
            'clave' => 'NEW-ORD',
            'orden' => 8,
        ]);
    }

    /** @test */
    public function edit_page_does_not_show_orden_field(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'SIN-ORD-UI',
            'nombre' => 'Sin orden UI',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 3,
            'estado' => true,
        ]));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.edit', [$this->indicatorType, $indicator]))
            ->assertOk()
            ->assertSee('Estado')
            ->assertDontSee('wire:model="orden"', false);
    }

    /** @test */
    public function parametrizador_can_access_create_and_edit_pages_via_http(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'HTTP-001',
                'nombre' => 'Página HTTP',
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.create', $this->indicatorType))
            ->assertOk()
            ->assertSee('Nuevo indicador');

        $this->get(route('metas.instrument.indicators.edit', [$this->indicatorType, $indicator]))
            ->assertOk()
            ->assertSee('Estado');
    }

    /** @test */
    public function visualizador_cannot_access_create_or_edit_pages_via_http(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'HTTP-403',
                'nombre' => 'Solo lectura',
            ])
        ));

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.create', $this->indicatorType))
            ->assertForbidden();

        $this->get(route('metas.instrument.indicators.edit', [$this->indicatorType, $indicator]))
            ->assertForbidden();
    }

    /** @test */
    public function visualizador_can_access_show_page_via_http(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'HTTP-SHOW',
                'nombre' => 'Ficha visualizador',
            ])
        ));

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $indicator]))
            ->assertOk()
            ->assertSee('Meta nacional');
    }

    /** @test */
    public function show_returns_404_when_indicator_year_not_in_session(): void
    {
        $otherYear = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'OTHER-YEAR-SHOW',
                'nombre' => 'Otro año show',
                'year_id' => $otherYear->id,
            ])
        ));

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $indicator]))
            ->assertNotFound();
    }

    /** @test */
    public function edit_returns_404_when_indicator_year_not_in_session(): void
    {
        $otherYear = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'OTHER-YEAR-EDIT',
                'nombre' => 'Otro año edit',
                'year_id' => $otherYear->id,
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.edit', [$this->indicatorType, $indicator]))
            ->assertNotFound();
    }

    /** @test */
    public function visualizador_catalog_list_hides_inactive_indicators(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'ACT-001',
                'nombre' => 'Indicador activo',
                'estado' => true,
            ])
        ));

        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'INACT-001',
                'nombre' => 'Indicador inactivo',
                'estado' => false,
            ])
        ));

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('ACT-001')
            ->assertDontSee('INACT-001');
    }

    /** @test */
    public function parametrizador_catalog_list_shows_inactive_indicators(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'INACT-PARAM',
                'nombre' => 'Solo parametrizador',
                'estado' => false,
            ])
        ));

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('INACT-PARAM')
            ->assertSee('Inactivo');
    }

    /** @test */
    public function visualizador_cannot_open_show_page_for_inactive_indicator(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'INACT-SHOW',
                'nombre' => 'Inactivo show',
                'estado' => false,
            ])
        ));

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $indicator]))
            ->assertNotFound();
    }

    /** @test */
    public function parametrizador_can_render_catalog_form_and_show_components(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'PAGE-001',
                'nombre' => 'Página show',
            ])
        ));

        $this->catalogFormTest()
            ->assertSee('Crear indicador');

        $this->actingAs($this->visualizador)->withSession(['indicator_year' => $this->year]);

        $this->catalogShowTest($indicator)
            ->assertSee('Meta nacional')
            ->assertDontSee('Editar');
    }

    private function catalogFormTest(?MetIndicator $indicator = null): Testable
    {
        $params = ['indicatorType' => $this->indicatorType];

        if ($indicator !== null) {
            $params['indicator'] = $indicator;
        }

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        return Livewire::test(IndicatorCatalogFormComponent::class, $params)
            ->assertStatus(200);
    }

    private function catalogShowTest(MetIndicator $indicator, ?User $user = null): Testable
    {
        $actor = $user ?? $this->visualizador;
        $this->actingAs($actor)->withSession(['indicator_year' => $this->year]);

        return Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicator,
        ])
            ->assertStatus(200);
    }

    /**
     * @param  Testable  $component
     */
    private function fillRequiredCatalogForm($component): void
    {
        $component
            ->set('metaValor', '85.50')
            ->set('metaOperador', '>=')
            ->set('umbralInferior', '0')
            ->set('umbralSuperior', '100')
            ->set('unidadMedida', UnidadMedida::Porcentaje->value)
            ->set('decimales', 2)
            ->set('decimalesModo', 'redondear')
            ->set('nivelAplicacion', 'Ámbito de prueba')
            ->set('estado', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalIndicatorAttributes(array $overrides = []): array
    {
        return array_merge([
            'indicator_type_id' => $this->indicatorType->id,
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $this->year->id,
            'estado' => true,
            'meta_valor' => 85.50,
            'meta_operador' => '>=',
            'umbral_inferior' => 0,
            'umbral_superior' => 100,
            'unidad_medida' => UnidadMedida::Porcentaje->value,
            'nivel_aplicacion' => 'Ámbito de prueba',
        ], $overrides);
    }

    private function createCenterCatalogDependencies(): void {}
}
