<?php

namespace Tests\Feature\Indicators;

use App\Enums\Comges\EvaluacionTipo;
use App\Enums\Indicators\IndicatorActivityAction;
use App\Enums\Indicators\IndicatorModuleType;
use App\Http\Controllers\Voyager\ComIndicatorController;
use App\Livewire\IndicatorTimelineComponent;
use App\Mail\IndicatorNoteNotification;
use App\Models\CenHieDependency;
use App\Models\ComEvaluation;
use App\Models\ComIndicator;
use App\Models\ComIndicatorSetting;
use App\Models\IndicatorActivity;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetIndicator;
use App\Models\MetIndicatorReferente;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Indicators\IndicatorActivityLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class IndicatorActivityTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private Year $year;

    private User $admin;

    private IndicatorType $indicatorType;

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

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['clave_indicador' => 'comges'],
            [
                'nombre_indicador' => 'COMGES',
                'route' => 'voyager.compromisos.index',
                'estado' => true,
                'orden' => 1,
            ]
        ));

        $this->enableMetasModuleAccess();
        $this->seedComgesBread($adminRole);
    }

    public function test_com_indicator_setting_update_logs_activity(): void
    {
        $indicatorId = $this->seedComIndicator();

        $setting = ComIndicatorSetting::unguarded(fn () => ComIndicatorSetting::create([
            'cen_hie_dependency_id' => 1,
            'com_indicator_id' => $indicatorId,
            'fecha_apertura' => '2026-01-01',
            'fecha_cierre' => '2026-12-31',
        ]));

        $setting->update(['mantener_abierto' => 1]);

        $this->assertDatabaseHas('indicator_activities', [
            'indicator_type' => IndicatorModuleType::Comges->value,
            'indicator_id' => $indicatorId,
            'action' => IndicatorActivityAction::ConfigChanged->value,
        ]);
    }

    public function test_com_evaluation_created_logs_activity(): void
    {
        $indicatorId = $this->seedComIndicator();
        $actionId = $this->seedComActionForIndicator($indicatorId);

        ComEvaluation::unguarded(fn () => ComEvaluation::create([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => 1,
            'tipo_evaluacion' => EvaluacionTipo::Autoevaluacion,
            'numerador' => 80,
            'denominador' => 100,
            'resultado' => 80,
            'cumplimiento' => 80,
        ]));

        $this->assertDatabaseHas('indicator_activities', [
            'indicator_type' => IndicatorModuleType::Comges->value,
            'indicator_id' => $indicatorId,
            'action' => IndicatorActivityAction::EvaluationLoaded->value,
        ]);
    }

    public function test_com_evaluation_updated_logs_activity(): void
    {
        $indicatorId = $this->seedComIndicator();
        $actionId = $this->seedComActionForIndicator($indicatorId);

        $evaluation = ComEvaluation::unguarded(fn () => ComEvaluation::create([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => 1,
            'tipo_evaluacion' => EvaluacionTipo::Autoevaluacion,
            'numerador' => 50,
            'denominador' => 100,
            'resultado' => 50,
            'cumplimiento' => 50,
        ]));

        IndicatorActivity::query()
            ->where('indicator_id', $indicatorId)
            ->where('action', IndicatorActivityAction::EvaluationLoaded)
            ->delete();

        $evaluation->update(['numerador' => 90, 'resultado' => 90, 'cumplimiento' => 90]);

        $this->assertDatabaseHas('indicator_activities', [
            'indicator_type' => IndicatorModuleType::Comges->value,
            'indicator_id' => $indicatorId,
            'action' => IndicatorActivityAction::EvaluationLoaded->value,
            'description' => 'Se actualizó la evaluación AUTOEVALUACION del indicador.',
        ]);
    }

    public function test_bulk_data_import_logs_single_summary_entry(): void
    {
        $indicator = $this->seedMetIndicator();
        $logger = app(IndicatorActivityLogger::class);

        $logger->logBulkDataImport($indicator->id, 1, 12, $this->admin->id);

        $this->assertDatabaseCount('indicator_activities', 1);
        $this->assertDatabaseHas('indicator_activities', [
            'indicator_type' => IndicatorModuleType::Metas->value,
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::DataLoaded->value,
            'user_id' => $this->admin->id,
            'description' => 'Se registró carga masiva de datos (12 registros).',
        ]);
    }

    public function test_automatic_logging_suppression_skips_per_row_com_evaluation_entries(): void
    {
        $indicatorId = $this->seedComIndicator();
        $actionId = $this->seedComActionForIndicator($indicatorId);

        $logger = app(IndicatorActivityLogger::class);
        $logger->withoutAutomaticLogging(function () use ($actionId): void {
            ComEvaluation::unguarded(fn () => ComEvaluation::create([
                'com_action_id' => $actionId,
                'cen_hie_dependency_id' => 1,
                'tipo_evaluacion' => EvaluacionTipo::Autoevaluacion,
                'numerador' => 10,
                'denominador' => 100,
                'resultado' => 10,
                'cumplimiento' => 10,
            ]));
        });

        $this->assertDatabaseCount('indicator_activities', 0);
    }

    public function test_met_indicator_referente_created_logs_activity(): void
    {
        $indicator = $this->seedMetIndicator();

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->admin->id,
        ]));

        $this->assertDatabaseHas('indicator_activities', [
            'indicator_type' => IndicatorModuleType::Metas->value,
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ReferentAssigned->value,
        ]);
    }

    public function test_manual_note_is_added_via_livewire_and_notifies_referente(): void
    {
        Mail::fake();

        $indicator = $this->seedMetIndicator();
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente@example.com',
        ]);
        $referente->role()->associate($referenteRole)->save();

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $referente->id,
        ]));

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->set('note', 'Nota de seguimiento operativo')
            ->set('notifyReferentes', true)
            ->call('addNote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('indicator_activities', [
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ManualNote->value,
            'description' => 'Nota de seguimiento operativo',
        ]);

        Mail::assertQueued(IndicatorNoteNotification::class);
    }

    public function test_manual_note_without_notify_toggle_does_not_email_referentes(): void
    {
        Mail::fake();

        $indicator = $this->seedMetIndicator();
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente@example.com',
        ]);
        $referente->role()->associate($referenteRole)->save();

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $referente->id,
        ]));

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->set('note', 'Nota sin aviso por correo')
            ->call('addNote')
            ->assertHasNoErrors();

        Mail::assertNothingQueued();
    }

    public function test_pinned_manual_note_is_stored_and_listed_first(): void
    {
        $indicator = $this->seedMetIndicator();

        IndicatorActivity::query()->create([
            'indicator_type' => IndicatorModuleType::Metas,
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ConfigChanged,
            'description' => 'Actividad automática reciente',
            'metadata' => ['cen_hie_dependency_id' => 1],
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        IndicatorActivity::query()->create([
            'indicator_type' => IndicatorModuleType::Metas,
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ManualNote,
            'description' => 'Nota antigua sin destacar',
            'metadata' => ['cen_hie_dependency_id' => 1],
            'is_pinned' => false,
            'user_id' => $this->admin->id,
            'created_at' => now()->subHours(2),
        ]);

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->set('note', 'Nota destacada prioritaria')
            ->set('mantenerDestacada', true)
            ->call('addNote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('indicator_activities', [
            'indicator_id' => $indicator->id,
            'description' => 'Nota destacada prioritaria',
            'is_pinned' => 1,
        ]);

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->assertSeeInOrder([
                'Nota destacada prioritaria',
                'Actividad automática reciente',
                'Nota antigua sin destacar',
            ])
            ->assertSee('Destacada', false);
    }

    public function test_referente_cannot_add_manual_note(): void
    {
        $indicator = $this->seedMetIndicator();
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($referenteRole)->save();

        Livewire::actingAs($referente)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->set('note', 'Intento de nota por referente')
            ->call('addNote')
            ->assertForbidden();

        $this->assertDatabaseMissing('indicator_activities', [
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ManualNote->value,
        ]);
    }

    public function test_control_gestion_can_pin_unpinned_manual_note(): void
    {
        $indicator = $this->seedMetIndicator();
        $cg = $this->controlGestionUser();

        $activity = $this->seedManualNote($indicator->id, isPinned: false);

        Livewire::actingAs($cg)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->call('togglePinned', $activity->id)
            ->assertHasNoErrors()
            ->assertSee('Nota destacada');

        $this->assertDatabaseHas('indicator_activities', [
            'id' => $activity->id,
            'is_pinned' => 1,
        ]);
    }

    public function test_control_gestion_can_unpin_pinned_manual_note(): void
    {
        $indicator = $this->seedMetIndicator();
        $cg = $this->controlGestionUser();

        $activity = $this->seedManualNote($indicator->id, isPinned: true);

        Livewire::actingAs($cg)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->call('togglePinned', $activity->id)
            ->assertHasNoErrors()
            ->assertSee('Se quitó el destacado de la nota.');

        $this->assertDatabaseHas('indicator_activities', [
            'id' => $activity->id,
            'is_pinned' => 0,
        ]);
    }

    public function test_referente_cannot_toggle_pinned_note(): void
    {
        $indicator = $this->seedMetIndicator();
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($referenteRole)->save();

        $activity = $this->seedManualNote($indicator->id);

        Livewire::actingAs($referente)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->call('togglePinned', $activity->id)
            ->assertForbidden();
    }

    public function test_visualizador_cannot_toggle_pinned_note(): void
    {
        $indicator = $this->seedMetIndicator();
        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create([
            'role_id' => $visualizadorRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $visualizador->role()->associate($visualizadorRole)->save();

        $activity = $this->seedManualNote($indicator->id);

        Livewire::actingAs($visualizador)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->call('togglePinned', $activity->id)
            ->assertForbidden();
    }

    public function test_admin_cannot_toggle_automatic_activity(): void
    {
        $indicator = $this->seedMetIndicator();

        $activity = IndicatorActivity::query()->create([
            'indicator_type' => IndicatorModuleType::Metas,
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ConfigChanged,
            'description' => 'Automática',
            'metadata' => ['cen_hie_dependency_id' => 1],
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->call('togglePinned', $activity->id)
            ->assertForbidden();
    }

    public function test_toggle_fails_for_note_from_other_ss(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Otro Servicio de Salud',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $indicator = $this->seedMetIndicator();
        $activity = $this->seedManualNote($indicator->id, ssId: 2);

        $component = Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ]);

        try {
            $component->call('togglePinned', $activity->id);
            // Livewire ≥4.4.6 convierte ModelNotFoundException a 404 en tests.
            $component->assertNotFound();
        } catch (ModelNotFoundException $exception) {
            // Livewire ≤4.4.5 rethrow; equivalente al 404 de producción.
            $this->assertSame(IndicatorActivity::class, $exception->getModel());
        }
    }

    public function test_non_admin_cannot_delete_automatic_activity(): void
    {
        $indicator = $this->seedMetIndicator();

        $activity = IndicatorActivity::query()->create([
            'indicator_type' => IndicatorModuleType::Metas,
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ConfigChanged,
            'description' => 'Automática',
            'metadata' => ['cen_hie_dependency_id' => 1],
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->call('deleteNote', $activity->id)
            ->assertForbidden();

        $this->assertDatabaseHas('indicator_activities', ['id' => $activity->id]);
    }

    public function test_admin_can_delete_manual_note(): void
    {
        $indicator = $this->seedMetIndicator();

        $activity = IndicatorActivity::query()->create([
            'indicator_type' => IndicatorModuleType::Metas,
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ManualNote,
            'description' => 'Nota temporal',
            'metadata' => ['cen_hie_dependency_id' => 1],
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->call('deleteNote', $activity->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('indicator_activities', ['id' => $activity->id]);
    }

    public function test_timeline_shows_empty_state_message(): void
    {
        $indicator = $this->seedMetIndicator();

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->assertSee('Sin actividades registradas');
    }

    public function test_timeline_pagination_uses_voyager_bootstrap_markup(): void
    {
        $indicator = $this->seedMetIndicator();

        for ($i = 1; $i <= 21; $i++) {
            IndicatorActivity::query()->create([
                'indicator_type' => IndicatorModuleType::Metas,
                'indicator_id' => $indicator->id,
                'action' => IndicatorActivityAction::ManualNote,
                'description' => 'Nota '.$i,
                'metadata' => ['cen_hie_dependency_id' => 1],
                'user_id' => $this->admin->id,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        Livewire::actingAs($this->admin)
            ->test(IndicatorTimelineComponent::class, [
                'indicatorType' => 'metas',
                'indicatorId' => $indicator->id,
            ])
            ->assertSee('metas-bitacora-pagination', false)
            ->assertSee('Mostrando')
            ->assertSee('de')
            ->assertSee('registros')
            ->assertSeeHtml('class="pagination metas-bitacora-pagination__pages"');
    }

    public function test_metas_indicator_show_includes_bitacora_tab(): void
    {
        $indicator = $this->seedMetIndicator();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicators.show', [
                $this->indicatorType,
                $indicator,
            ]))
            ->assertOk()
            ->assertSee('Ficha del indicador', false)
            ->assertSee('Bitácora', false);
    }

    public function test_comges_indicator_read_includes_bitacora_tab_for_2026(): void
    {
        $indicatorId = $this->seedComIndicator();
        $this->seedComInstrumentSetting();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/indicadores-comges/'.$indicatorId)
            ->assertOk()
            ->assertSee('Información', false)
            ->assertSee('Bitácora', false);
    }

    private function seedComIndicator(): int
    {
        $comgesTypeId = (int) IndicatorType::query()->where('clave_indicador', 'comges')->value('id');

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::updateOrCreate(
            [
                'indicator_type_id' => $comgesTypeId,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            ['habilitado' => true, 'etapa_vigente' => 'AUTOEVALUACION']
        ));

        $suffix = bin2hex(random_bytes(4));

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $comgesTypeId,
            'clave' => 'C-'.$suffix,
            'nombre' => 'Compromiso',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $this->year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'IND-'.$suffix,
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedComInstrumentSetting(): void
    {
        $comgesTypeId = (int) IndicatorType::query()->where('clave_indicador', 'comges')->value('id');

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::updateOrCreate(
            [
                'indicator_type_id' => $comgesTypeId,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
            ],
            ['habilitado' => true, 'etapa_vigente' => 'AUTOEVALUACION']
        ));
    }

    private function seedMetIndicator(): MetIndicator
    {
        return MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-ACT-'.bin2hex(random_bytes(2)),
            'nombre' => 'Indicador actividad',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));
    }

    private function controlGestionUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        return $user;
    }

    private function seedManualNote(int $indicatorId, bool $isPinned = false, int $ssId = 1): IndicatorActivity
    {
        return IndicatorActivity::query()->create([
            'indicator_type' => IndicatorModuleType::Metas,
            'indicator_id' => $indicatorId,
            'action' => IndicatorActivityAction::ManualNote,
            'description' => 'Nota manual de prueba',
            'metadata' => ['cen_hie_dependency_id' => $ssId],
            'is_pinned' => $isPinned,
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);
    }

    private function enableMetasModuleAccess(): void
    {
        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => 1, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            [
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => true,
            ],
        ));
    }

    private function seedComgesBread(Role $role): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        $dataType = DataType::unguarded(function (): DataType {
            return DataType::query()->updateOrCreate(
                ['slug' => 'indicadores-comges'],
                [
                    'name' => 'com_indicators',
                    'display_name_singular' => 'Indicador COMGES',
                    'display_name_plural' => 'Indicadores COMGES',
                    'model_name' => ComIndicator::class,
                    'controller' => ComIndicatorController::class,
                    'generate_permissions' => 0,
                    'server_side' => 0,
                ]
            );
        });

        Permission::unguarded(function () use ($dataType, $role): void {
            foreach (['browse', 'read', 'edit', 'add', 'delete'] as $action) {
                $permission = Permission::firstOrCreate([
                    'table_name' => $dataType->name,
                    'key' => $action.'_'.$dataType->name,
                ]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });

        $this->registerVoyagerBreadPolicyForModel(ComIndicator::class);
        $this->grantBrowseAdminPermissionToRole($role);
    }

    private function seedComActionForIndicator(int $indicatorId): int
    {
        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $linkId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
