<?php

namespace Tests\Feature\MetasInstrument;

use App\Livewire\MetasInstrument\DataFieldsComponent;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DataFieldsComponentTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $parametrizador;

    private User $adminUser;

    private User $controlGestion;

    private MetIndicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

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

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->parametrizador = User::factory()->create(['role_id' => $parametrizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $this->parametrizador->role()->associate($parametrizadorRole)->save();

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'IND-01',
            'nombre' => 'Indicador de prueba',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => 1,
        ]));
    }

    /** @test */
    public function parametrizador_can_render_data_fields_component(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->assertStatus(200);
    }

    /** @test */
    public function admin_can_render_data_fields_component(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->assertStatus(200);
    }

    /** @test */
    public function control_gestion_is_forbidden_from_data_fields(): void
    {
        Livewire::actingAs($this->controlGestion)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->assertStatus(403);
    }

    /** @test */
    public function can_add_field_with_valid_data(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Egresos hospitalarios')
            ->set('newFieldComponente', 'numerador')
            ->set('newFieldInputType', 'mensual')
            ->call('addField')
            ->assertSet('fields', function (array $fields) {
                return count($fields) === 1 && $fields[0]['label'] === 'Egresos hospitalarios';
            });
    }

    /** @test */
    public function add_field_requires_label(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', '')
            ->call('addField')
            ->assertHasErrors(['newFieldLabel']);
    }

    /** @test */
    public function can_remove_field(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Campo A')
            ->call('addField')
            ->call('removeField', 0)
            ->assertSet('fields', []);
    }

    /** @test */
    public function save_all_persists_fields_to_database(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Numerador')
            ->set('newFieldComponente', 'numerador')
            ->set('newFieldInputType', 'mensual')
            ->call('addField')
            ->set('newFieldLabel', 'Denominador')
            ->set('newFieldComponente', 'denominador')
            ->set('newFieldInputType', 'mensual')
            ->call('addField')
            ->call('saveAll');

        $this->assertDatabaseHas('met_data_fields', [
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
        ]);

        $this->assertDatabaseHas('met_data_fields', [
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
        ]);
    }

    /** @test */
    public function save_all_assigns_sequential_variable_keys(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Campo 1')
            ->call('addField')
            ->set('newFieldLabel', 'Campo 2')
            ->call('addField')
            ->set('newFieldLabel', 'Campo 3')
            ->call('addField')
            ->call('saveAll');

        $this->assertDatabaseHas('met_data_fields', ['met_indicator_id' => $this->indicator->id, 'variable_key' => 'A']);
        $this->assertDatabaseHas('met_data_fields', ['met_indicator_id' => $this->indicator->id, 'variable_key' => 'B']);
        $this->assertDatabaseHas('met_data_fields', ['met_indicator_id' => $this->indicator->id, 'variable_key' => 'C']);
    }

    /** @test */
    public function save_all_persists_formula_json(): void
    {
        $formulaTokens = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
        ];

        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Numerador')
            ->call('addField')
            ->set('newFieldLabel', 'Denominador')
            ->call('addField')
            ->set('formulaJson', json_encode($formulaTokens))
            ->call('saveAll');

        $this->assertDatabaseHas('met_formulas', [
            'met_indicator_id' => $this->indicator->id,
        ]);

        $formula = MetFormula::where('met_indicator_id', $this->indicator->id)->first();
        $this->assertNotNull($formula);
        $this->assertCount(3, $formula->expression);
    }

    /** @test */
    public function move_up_reorders_fields(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Primero')
            ->call('addField')
            ->set('newFieldLabel', 'Segundo')
            ->call('addField')
            ->call('moveUp', 1)
            ->assertSet('fields', function (array $fields) {
                return $fields[0]['label'] === 'Segundo' && $fields[1]['label'] === 'Primero';
            });
    }

    /** @test */
    public function reorder_reassigns_variable_keys_on_save(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Primero')
            ->call('addField')
            ->set('newFieldLabel', 'Segundo')
            ->call('addField')
            ->call('moveUp', 1)
            ->call('saveAll');

        $fieldA = MetDataField::where('met_indicator_id', $this->indicator->id)
            ->where('variable_key', 'A')
            ->first();

        $this->assertNotNull($fieldA);
        $this->assertSame('Segundo', $fieldA->label);
    }

    /** @test */
    public function save_all_clears_formula_when_empty_json(): void
    {
        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [['type' => 'var', 'key' => 'A']],
            'description' => 'A',
        ]));

        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('formulaJson', '[]')
            ->call('saveAll');

        $this->assertDatabaseMissing('met_formulas', ['met_indicator_id' => $this->indicator->id]);
    }

    /** @test */
    public function save_all_rechaza_formula_min_con_argumento_vacio(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Numerador')
            ->call('addField')
            ->set('formulaJson', json_encode([
                [
                    'type' => 'fn',
                    'name' => 'min',
                    'args' => [
                        [['type' => 'var', 'key' => 'A']],
                        [],
                    ],
                ],
            ]))
            ->call('saveAll')
            ->assertHasErrors(['formulaJson'])
            ->assertSee('alert alert-danger', false)
            ->assertSee('El argumento 2 de min() no puede estar vacío.', false)
            ->assertSee('Constructor de fórmula', false);
    }

    /** @test */
    public function save_all_rechaza_formula_division_por_cero_muestra_alerta(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Numerador')
            ->call('addField')
            ->set('formulaJson', json_encode([
                ['type' => 'lit', 'value' => '1'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'lit', 'value' => '0'],
            ]))
            ->call('saveAll')
            ->assertHasErrors(['formulaJson'])
            ->assertSee('alert alert-danger', false)
            ->assertSee('La fórmula contiene una división por cero inválida.', false)
            ->assertSee('Constructor de fórmula', false);
    }

    /** @test */
    public function save_all_persiste_formula_min_valida(): void
    {
        Livewire::actingAs($this->parametrizador)
            ->test(DataFieldsComponent::class, ['indicatorId' => $this->indicator->id])
            ->set('newFieldLabel', 'Numerador')
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
            ->call('saveAll')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_formulas', [
            'met_indicator_id' => $this->indicator->id,
            'description' => 'min(A, 1)',
        ]);
    }
}
