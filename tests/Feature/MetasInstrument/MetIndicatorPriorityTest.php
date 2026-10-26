<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Metas\MetIndicatorPriority;
use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Livewire\MetasInstrument\IndicatorCatalogFormComponent;
use App\Livewire\MetasInstrument\IndicatorCatalogShowComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MetIndicatorPriorityTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $type19664;

    private IndicatorType $type18834;

    private IndicatorTypeSetting $setting19664;

    private User $controlGestion;

    private User $parametrizador;

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

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->parametrizador = User::factory()->create(['role_id' => $paramRole->id, 'cen_hie_dependency_id' => 1]);
        $this->parametrizador->role()->associate($paramRole)->save();

        $this->type19664 = $this->indicatorTypeByRoute('metas-19664', ['estado' => true]);
        $this->type18834 = $this->indicatorTypeByRoute('metas-18834', ['estado' => true]);

        $this->setting19664 = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->type19664->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'FORMULACION',
            'habilitado' => 1,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->type18834->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'FORMULACION',
            'habilitado' => 1,
        ]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->type19664->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 1,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->type18834->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 1,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        $this->center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-P',
            'nombre_corto' => 'Hospital Prioridad',
            'cen_hie_dependency_id' => 1,
        ]);
    }

    /** @test */
    public function migration_default_prioridad_is_sin_prioridad(): void
    {
        $indicator = $this->createIndicator($this->type19664, 'PRI-DEF');

        $this->assertSame(MetIndicatorPriority::SinPrioridad, $indicator->fresh()->prioridad);
    }

    /** @test */
    public function catalog_form_loads_and_accepts_prioridad(): void
    {
        $indicator = $this->createIndicator($this->type19664, 'PRI-FORM', MetIndicatorPriority::Media);

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogFormComponent::class, [
            'indicatorTypeId' => $this->type19664->id,
            'indicatorId' => $indicator->id,
        ])
            ->assertSet('prioridad', MetIndicatorPriority::Media->value)
            ->set('prioridad', MetIndicatorPriority::Alta->value)
            ->assertSet('prioridad', MetIndicatorPriority::Alta->value);

        MetIndicator::unguarded(fn () => $indicator->update([
            'prioridad' => MetIndicatorPriority::Alta,
        ]));

        $this->assertSame(MetIndicatorPriority::Alta, $indicator->fresh()->prioridad);
    }

    /** @test */
    public function catalog_show_displays_prioridad(): void
    {
        $indicator = $this->createIndicator($this->type19664, 'PRI-SHOW', MetIndicatorPriority::Alta);

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->type19664,
            'indicator' => $indicator,
        ])
            ->assertStatus(200)
            ->assertSee('Prioridad')
            ->assertSee('Prioridad Alta');
    }

    /** @test */
    public function catalog_form_and_show_hide_prioridad_outside_19664(): void
    {
        $indicator = $this->createIndicator($this->type18834, 'PRI-HIDE', MetIndicatorPriority::Alta);

        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogFormComponent::class, [
            'indicatorTypeId' => $this->type18834->id,
            'indicatorId' => $indicator->id,
        ])
            ->assertDontSeeHtml('wire:model="prioridad"');

        Livewire::test(IndicatorCatalogShowComponent::class, [
            'indicatorType' => $this->type18834,
            'indicator' => $indicator,
        ])
            ->assertStatus(200)
            ->assertDontSee('Prioridad Alta');
    }

    /** @test */
    public function convenio_19664_shows_priority_tags_except_sin_prioridad(): void
    {
        $this->includeCenter($this->type19664);

        $alta = $this->createIndicator($this->type19664, 'PRI-A', MetIndicatorPriority::Alta);
        $media = $this->createIndicator($this->type19664, 'PRI-M', MetIndicatorPriority::Media);
        $baja = $this->createIndicator($this->type19664, 'PRI-B', MetIndicatorPriority::Baja);
        $sin = $this->createIndicator($this->type19664, 'PRI-S', MetIndicatorPriority::SinPrioridad);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->type19664->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->center->id,
        ])
            ->assertSee('Prioridad Alta')
            ->assertSee('Prioridad Media')
            ->assertSee('Prioridad Baja')
            ->assertSee($sin->nombre)
            ->assertDontSee('>Sin prioridad<');
    }

    /** @test */
    public function convenio_18834_does_not_show_priority_tags(): void
    {
        $this->includeCenter($this->type18834);
        $this->createIndicator($this->type18834, 'PRI-188', MetIndicatorPriority::Alta);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->type18834->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->center->id,
        ])
            ->assertDontSee('Prioridad Alta');
    }

    /** @test */
    public function save_config_19664_rejects_when_alta_not_strictly_greater_than_no_alta(): void
    {
        $this->includeCenter($this->type19664);

        $alta = $this->createIndicator($this->type19664, 'PRI-FAIL-A', MetIndicatorPriority::Alta);
        $media = $this->createIndicator($this->type19664, 'PRI-FAIL-M', MetIndicatorPriority::Media);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->type19664->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->center->id,
        ])
            ->set("configs.{$alta->id}.aplica", true)
            ->set("configs.{$alta->id}.seleccionado", true)
            ->set("configs.{$alta->id}.ponderacion", '50')
            ->set("configs.{$media->id}.aplica", true)
            ->set("configs.{$media->id}.seleccionado", true)
            ->set("configs.{$media->id}.ponderacion", '50')
            ->call('saveConfig')
            ->assertHasErrors('configGeneral');

        $this->assertDatabaseMissing('met_indicator_configurations', [
            'met_indicator_id' => $alta->id,
            'cen_center_id' => $this->center->id,
            'seleccionado' => 1,
            'ponderacion' => 50,
        ]);
    }

    /** @test */
    public function save_config_19664_accepts_when_alta_strictly_greater_than_no_alta(): void
    {
        $this->includeCenter($this->type19664);

        $alta = $this->createIndicator($this->type19664, 'PRI-OK-A', MetIndicatorPriority::Alta);
        $media = $this->createIndicator($this->type19664, 'PRI-OK-M', MetIndicatorPriority::Media);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->type19664->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->center->id,
        ])
            ->set("configs.{$alta->id}.aplica", true)
            ->set("configs.{$alta->id}.seleccionado", true)
            ->set("configs.{$alta->id}.ponderacion", '60')
            ->set("configs.{$media->id}.aplica", true)
            ->set("configs.{$media->id}.seleccionado", true)
            ->set("configs.{$media->id}.ponderacion", '40')
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $alta->id,
            'cen_center_id' => $this->center->id,
            'seleccionado' => 1,
            'ponderacion' => 60,
        ]);
    }

    /** @test */
    public function save_config_19664_skips_priority_rule_when_only_alta_selected(): void
    {
        $this->includeCenter($this->type19664);

        $alta = $this->createIndicator($this->type19664, 'PRI-ONLY-A', MetIndicatorPriority::Alta);
        $this->createIndicator($this->type19664, 'PRI-ONLY-M', MetIndicatorPriority::Media);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->type19664->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->center->id,
        ])
            ->set("configs.{$alta->id}.aplica", true)
            ->set("configs.{$alta->id}.seleccionado", true)
            ->set("configs.{$alta->id}.ponderacion", '100')
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');
    }

    /** @test */
    public function save_config_19664_ignores_unselected_indicators_in_priority_rule(): void
    {
        $this->includeCenter($this->type19664);

        $alta = $this->createIndicator($this->type19664, 'PRI-UNSEL-A', MetIndicatorPriority::Alta);
        $mediaSel = $this->createIndicator($this->type19664, 'PRI-UNSEL-M', MetIndicatorPriority::Media);
        $mediaHighUnselected = $this->createIndicator($this->type19664, 'PRI-UNSEL-X', MetIndicatorPriority::Media);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->type19664->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->center->id,
        ])
            ->set("configs.{$alta->id}.aplica", true)
            ->set("configs.{$alta->id}.seleccionado", true)
            ->set("configs.{$alta->id}.ponderacion", '55')
            ->set("configs.{$mediaSel->id}.aplica", true)
            ->set("configs.{$mediaSel->id}.seleccionado", true)
            ->set("configs.{$mediaSel->id}.ponderacion", '45')
            ->set("configs.{$mediaHighUnselected->id}.aplica", true)
            ->set("configs.{$mediaHighUnselected->id}.seleccionado", false)
            ->set("configs.{$mediaHighUnselected->id}.ponderacion", '90')
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');
    }

    /** @test */
    public function save_config_18834_does_not_apply_priority_ponderacion_rule(): void
    {
        $this->includeCenter($this->type18834);

        $alta = $this->createIndicator($this->type18834, 'PRI-188-A', MetIndicatorPriority::Alta);
        $media = $this->createIndicator($this->type18834, 'PRI-188-M', MetIndicatorPriority::Media);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->type18834->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->center->id,
        ])
            ->set("configs.{$alta->id}.aplica", true)
            ->set("configs.{$alta->id}.seleccionado", true)
            ->set("configs.{$alta->id}.ponderacion", '40')
            ->set("configs.{$media->id}.aplica", true)
            ->set("configs.{$media->id}.seleccionado", true)
            ->set("configs.{$media->id}.ponderacion", '60')
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $alta->id,
            'cen_center_id' => $this->center->id,
            'ponderacion' => 40,
        ]);
    }

    private function includeCenter(IndicatorType $type): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $type->id,
            'cen_hie_dependency_id' => 1,
        ]));
    }

    private function createIndicator(
        IndicatorType $type,
        string $clave,
        MetIndicatorPriority $prioridad = MetIndicatorPriority::SinPrioridad,
    ): MetIndicator {
        return MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type->id,
            'clave' => $clave,
            'nombre' => 'Indicador '.$clave,
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 50.0,
            'meta_operador' => '>=',
            'nivel_aplicacion' => 'Establecimiento',
            'unidad_medida' => '%',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
            'prioridad' => $prioridad,
        ]));
    }
}
