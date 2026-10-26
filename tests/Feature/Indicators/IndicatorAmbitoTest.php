<?php

namespace Tests\Feature\Indicators;

use App\Livewire\MetasInstrument\IndicatorCatalogComponent;
use App\Livewire\MetasInstrument\IndicatorCatalogFormComponent;
use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\IndicatorAmbito;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetIndicator;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndicatorAmbitoTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $parametrizador;

    private IndicatorType $indicatorType;

    protected function setUp(): void
    {
        parent::setUp();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $this->parametrizador = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->parametrizador->role()->associate($paramRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

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

    public function test_seeder_creates_default_ambitos(): void
    {
        $this->artisan('db:seed', ['--class' => 'IndicatorAmbitoSeeder']);

        $this->assertDatabaseHas('indicator_ambitos', ['slug' => 'hospitalizacion']);
        $this->assertDatabaseHas('indicator_ambitos', ['slug' => 'ges']);
    }

    public function test_met_indicator_can_sync_ambitos(): void
    {
        $ambito = IndicatorAmbito::query()->where('slug', 'urgencia')->firstOrFail();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MT-001',
            'nombre' => 'Indicador prueba',
            'meta_valor' => 80,
            'meta_operador' => '>=',
            'nivel_aplicacion' => 'Nacional',
            'unidad_medida' => '%',
            'estado' => true,
        ]));

        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, [$ambito->id]);

        $this->assertTrue($indicator->fresh()->indicatorAmbitos->contains('id', $ambito->id));
    }

    public function test_indicator_without_ambito_remains_visible_in_catalog(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MT-SIN',
            'nombre' => 'Sin ambito',
            'meta_valor' => 80,
            'meta_operador' => '>=',
            'nivel_aplicacion' => 'Nacional',
            'unidad_medida' => '%',
            'estado' => true,
        ]));

        $this->actingAs($this->parametrizador);
        session(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->set('ambitoFilter', 'none')
            ->assertSee('MT-SIN');
    }

    public function test_catalog_filters_by_ambito(): void
    {
        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();

        $tagged = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MT-TAG',
            'nombre' => 'Con ambito',
            'meta_valor' => 80,
            'meta_operador' => '>=',
            'nivel_aplicacion' => 'Nacional',
            'unidad_medida' => '%',
            'estado' => true,
        ]));

        MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MT-OTRO',
            'nombre' => 'Otro',
            'meta_valor' => 80,
            'meta_operador' => '>=',
            'nivel_aplicacion' => 'Nacional',
            'unidad_medida' => '%',
            'estado' => true,
        ]));

        app(IndicatorAmbitoAssignmentService::class)->sync($tagged, [$ambito->id]);

        $this->actingAs($this->parametrizador);
        session(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->set('ambitoFilter', (string) $ambito->id)
            ->assertSee('MT-TAG')
            ->assertDontSee('MT-OTRO');
    }

    public function test_form_saves_ambitos_without_blocking_when_empty(): void
    {
        $this->actingAs($this->parametrizador);
        session(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogFormComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->set('clave', 'MT-NEW')
            ->set('nombre', 'Nuevo')
            ->set('metaValor', '85')
            ->set('metaOperador', '>=')
            ->set('nivelAplicacion', 'SS')
            ->set('unidadMedida', '%')
            ->set('selectedAmbitoIds', [])
            ->call('save')
            ->assertHasNoErrors();

        $indicator = MetIndicator::query()->where('clave', 'MT-NEW')->first();
        $this->assertNotNull($indicator);
        $this->assertCount(0, $indicator->indicatorAmbitos);
    }

    public function test_form_saves_selected_ambitos_via_picker(): void
    {
        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();

        $this->actingAs($this->parametrizador);
        session(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogFormComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->set('clave', 'MT-AMB')
            ->set('nombre', 'Con ámbitos')
            ->set('metaValor', '85')
            ->set('metaOperador', '>=')
            ->set('nivelAplicacion', 'SS')
            ->set('unidadMedida', '%')
            ->set('selectedAmbitoIds', [(string) $ambito->id])
            ->call('save')
            ->assertHasNoErrors();

        $indicator = MetIndicator::query()->where('clave', 'MT-AMB')->firstOrFail();
        $this->assertTrue($indicator->indicatorAmbitos->contains('id', $ambito->id));
    }

    public function test_dashboard_payload_includes_ambito_groups(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($cgRole)->save();

        session(['indicator_year' => $this->year]);

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'year_id' => $this->year->id,
            'clave' => 'C1',
            'nombre' => 'Compromiso 1',
            'orden' => 1,
        ]));

        $comgesType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => 1, 'module_key' => 'comges'],
            ['enabled' => true],
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $comgesType->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            ['habilitado' => true],
        ));

        ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'I1',
            'nombre' => 'Indicador COMGES',
            'ponderacion_indicador' => 100,
        ]));

        session(['indicator_year' => $this->year]);

        $this->actingAs($user);

        $payload = app(NonAdminDashboardService::class)->forUser($user);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('ambitos', $payload);
        $this->assertNotEmpty($payload['ambitos']);
        $labels = array_column($payload['ambitos'], 'label');
        $this->assertContains('Sin ámbito', $labels);
        $this->assertNotContains('Hospitalización', $labels);
    }

    public function test_dashboard_hides_ambitos_without_indicators(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($cgRole)->save();

        session(['indicator_year' => $this->year]);
        $this->actingAs($user);

        $payload = app(NonAdminDashboardService::class)->forUser($user);

        $slugs = array_column($payload['ambitos'] ?? [], 'slug');
        $this->assertNotContains('hospitalizacion', $slugs);
        $this->assertNotContains('ges', $slugs);
    }
}
