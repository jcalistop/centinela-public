<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Enums\Metas\UnidadMedida;
use App\Livewire\MetasInstrument\IndicatorCatalogComponent;
use App\Models\CenHieDependency;
use App\Models\IndicatorAmbito;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class IndicatorCatalogDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private Year $destYear;

    private IndicatorType $indicatorType;

    private IndicatorType $destIndicatorType;

    private User $parametrizador;

    private User $adminUser;

    private User $controlGestion;

    private User $visualizador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->destYear = Year::unguarded(fn () => Year::create(['ano' => 2027, 'default' => 0]));

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

        $this->indicatorType = $this->indicatorTypeByRoute('metas-18834', ['estado' => true]);
        $this->destIndicatorType = $this->indicatorTypeByRoute('metas-18834', ['estado' => true]);

        $this->enableMetasModuleAccessForDependency(1, $this->indicatorType);
    }

    private function enableMetasModuleAccessForDependency(int $depId, ?IndicatorType $type = null): void
    {
        $type ??= $this->indicatorType;

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => $depId, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $type->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $depId,
            ],
            [
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => true,
            ],
        ));
    }

    private function catalogComponentTest(?User $user = null): Testable
    {
        $actor = $user ?? $this->parametrizador;
        $this->actingAs($actor)->withSession(['indicator_year' => $this->year]);

        return Livewire::test(IndicatorCatalogComponent::class)
            ->assertStatus(200);
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

    /** @test */
    public function parametrizador_sees_duplicate_button(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-SRC', 'nombre' => 'Origen duplicación'])
        ));

        $this->catalogComponentTest()
            ->assertSeeHtml('wire:click="openDuplicateModal('.$indicator->id.')"');
    }

    /** @test */
    public function admin_sees_duplicate_button(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-ADM', 'nombre' => 'Origen admin'])
        ));

        $this->catalogComponentTest($this->adminUser)
            ->assertSeeHtml('wire:click="openDuplicateModal('.$indicator->id.')"');
    }

    /** @test */
    public function control_gestion_does_not_see_duplicate_button(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-CG', 'nombre' => 'CG no puede duplicar'])
        ));

        $this->catalogComponentTest($this->controlGestion)
            ->assertDontSeeHtml('wire:click="openDuplicateModal');
    }

    /** @test */
    public function visualizador_does_not_see_duplicate_button(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-VIS', 'nombre' => 'Vis no puede duplicar'])
        ));

        $this->catalogComponentTest($this->visualizador)
            ->assertDontSeeHtml('wire:click="openDuplicateModal');
    }

    /** @test */
    public function duplicate_creates_indicator_with_same_attributes(): void
    {
        $source = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'DUP-SRC-01',
                'nombre' => 'Indicador original',
                'objetivo' => 'Objetivo de prueba',
                'meta_anual_descripcion' => 'Meta anual descripción',
                'meta_valor' => 90.0000,
                'meta_operador' => '>=',
                'umbral_inferior' => 10.00,
                'umbral_superior' => 95.00,
                'nivel_aplicacion' => 'Nivel prueba',
                'exclusiones' => 'Excluir centros X',
                'unidad_medida' => '%',
                'decimales' => 3,
                'decimales_modo' => 'truncar',
                'estado' => true,
                'orden' => 5,
            ])
        ));

        $newClave = 'DUP-DEST-01';

        $this->catalogComponentTest()
            ->call('openDuplicateModal', $source->id)
            ->assertSet('showDuplicateModal', true)
            ->assertSet('duplicateSourceId', $source->id)
            ->set('duplicateYearId', $this->destYear->id)
            ->set('duplicateIndicatorTypeId', $this->destIndicatorType->id)
            ->set('duplicateClave', $newClave)
            ->call('duplicateIndicator')
            ->assertHasNoErrors();

        /** @var MetIndicator $dest */
        $dest = MetIndicator::query()->where('clave', $newClave)->first();
        $this->assertNotNull($dest);

        $this->assertNotEquals($source->id, $dest->id);
        $this->assertEquals($this->destYear->id, $dest->year_id);
        $this->assertEquals($this->destIndicatorType->id, $dest->indicator_type_id);
        $this->assertEquals($source->nombre, $dest->nombre);
        $this->assertEquals($source->objetivo, $dest->objetivo);
        $this->assertEquals($source->meta_anual_descripcion, $dest->meta_anual_descripcion);
        $this->assertEquals((float) $source->meta_valor, (float) $dest->meta_valor);
        $this->assertEquals($source->meta_operador?->value, $dest->meta_operador?->value);
        $this->assertEquals((float) $source->umbral_inferior, (float) $dest->umbral_inferior);
        $this->assertEquals((float) $source->umbral_superior, (float) $dest->umbral_superior);
        $this->assertEquals($source->nivel_aplicacion, $dest->nivel_aplicacion);
        $this->assertEquals($source->exclusiones, $dest->exclusiones);
        $this->assertEquals($source->unidad_medida, $dest->unidad_medida);
        $this->assertEquals($source->decimales, $dest->decimales);
        $this->assertEquals($source->decimales_modo?->value, $dest->decimales_modo?->value);
        $this->assertEquals($source->estado, $dest->estado);
        $this->assertEquals(1, $dest->orden);
    }

    /** @test */
    public function duplicate_copies_data_fields(): void
    {
        $source = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-FLD-SRC', 'nombre' => 'Indicador con campos'])
        ));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $source->id,
            'label' => 'Numerador A',
            'variable_key' => 'A',
            'componente' => DataFieldComponente::Numerador->value,
            'input_type' => DataFieldInputType::Mensual->value,
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $source->id,
            'label' => 'Denominador B',
            'variable_key' => 'B',
            'componente' => DataFieldComponente::Denominador->value,
            'input_type' => DataFieldInputType::Mensual->value,
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        $newClave = 'DUP-FLD-DST';

        $this->catalogComponentTest()
            ->call('openDuplicateModal', $source->id)
            ->set('duplicateClave', $newClave)
            ->call('duplicateIndicator')
            ->assertHasNoErrors();

        /** @var MetIndicator $dest */
        $dest = MetIndicator::query()->where('clave', $newClave)->first();
        $this->assertNotNull($dest);

        $destFields = MetDataField::query()
            ->where('met_indicator_id', $dest->id)
            ->orderBy('orden')
            ->get();

        $this->assertCount(2, $destFields);
        $this->assertEquals('Numerador A', $destFields[0]->label);
        $this->assertEquals('A', $destFields[0]->variable_key);
        $this->assertEquals('Denominador B', $destFields[1]->label);
        $this->assertEquals('B', $destFields[1]->variable_key);
    }

    /** @test */
    public function duplicate_copies_formula(): void
    {
        $source = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-FRM-SRC', 'nombre' => 'Indicador con fórmula'])
        ));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $source->id,
            'label' => 'Campo A',
            'variable_key' => 'A',
            'componente' => DataFieldComponente::Numerador->value,
            'input_type' => DataFieldInputType::Mensual->value,
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $source->id,
            'label' => 'Campo B',
            'variable_key' => 'B',
            'componente' => DataFieldComponente::Denominador->value,
            'input_type' => DataFieldInputType::Mensual->value,
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        $expression = [
            ['type' => 'var', 'key' => 'A'],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => 'B'],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ];

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $source->id,
            'expression' => $expression,
            'description' => '( A / B ) * 100',
        ]));

        $newClave = 'DUP-FRM-DST';

        $this->catalogComponentTest()
            ->call('openDuplicateModal', $source->id)
            ->set('duplicateClave', $newClave)
            ->call('duplicateIndicator')
            ->assertHasNoErrors();

        /** @var MetIndicator $dest */
        $dest = MetIndicator::query()->where('clave', $newClave)->first();
        $this->assertNotNull($dest);

        $destFormula = MetFormula::query()->where('met_indicator_id', $dest->id)->first();
        $this->assertNotNull($destFormula);
        $this->assertEquals($expression, $destFormula->expression);
        $this->assertNotEmpty($destFormula->description);
    }

    /** @test */
    public function duplicate_copies_ambitos(): void
    {
        $ambito = IndicatorAmbito::unguarded(fn () => IndicatorAmbito::firstOrCreate(
            ['slug' => 'hospitalizacion-duplicate'],
            [
                'nombre' => 'Hospitalización',
                'orden' => 1,
                'estado' => true,
            ]
        ));

        $source = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-AMB-SRC', 'nombre' => 'Indicador con ámbito'])
        ));

        $source->indicatorAmbitos()->sync([$ambito->id]);

        $newClave = 'DUP-AMB-DST';

        $this->catalogComponentTest()
            ->call('openDuplicateModal', $source->id)
            ->set('duplicateClave', $newClave)
            ->call('duplicateIndicator')
            ->assertHasNoErrors();

        /** @var MetIndicator $dest */
        $dest = MetIndicator::query()->where('clave', $newClave)->first();
        $this->assertNotNull($dest);

        $destAmbitoIds = $dest->indicatorAmbitos()->pluck('indicator_ambitos.id')->toArray();
        $this->assertEquals([$ambito->id], $destAmbitoIds);
    }

    /** @test */
    public function duplicate_fails_with_existing_clave(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'EXISTING', 'nombre' => 'Indicador existente'])
        ));

        $source = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-CLAVE-SRC', 'nombre' => 'Fuente duplicación'])
        ));

        $this->catalogComponentTest()
            ->call('openDuplicateModal', $source->id)
            ->set('duplicateClave', 'EXISTING')
            ->assertSet('duplicateClaveExists', true)
            ->call('duplicateIndicator')
            ->assertHasErrors(['duplicateClave']);
    }

    /** @test */
    public function duplicate_indicator_sets_correct_orden(): void
    {
        // Crear un indicador en el destino para que el orden no sea 1
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes([
                'clave' => 'DEST-EXISTING',
                'nombre' => 'Existente en destino',
                'year_id' => $this->destYear->id,
                'indicator_type_id' => $this->destIndicatorType->id,
                'orden' => 1,
            ])
        ));

        $source = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-ORD-SRC', 'nombre' => 'Fuente orden'])
        ));

        $this->catalogComponentTest()
            ->call('openDuplicateModal', $source->id)
            ->set('duplicateYearId', $this->destYear->id)
            ->set('duplicateIndicatorTypeId', $this->destIndicatorType->id)
            ->set('duplicateClave', 'DUP-ORD-DST')
            ->call('duplicateIndicator')
            ->assertHasNoErrors();

        /** @var MetIndicator $dest */
        $dest = MetIndicator::query()->where('clave', 'DUP-ORD-DST')->first();
        $this->assertNotNull($dest);
        $this->assertEquals(2, $dest->orden);
    }

    /** @test */
    public function non_parametrizador_cannot_open_duplicate_modal(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-403', 'nombre' => 'Indicador 403'])
        ));

        $this->catalogComponentTest($this->visualizador)
            ->call('openDuplicateModal', $indicator->id)
            ->assertStatus(403);
    }

    /** @test */
    public function reactive_clave_validation_shows_warning(): void
    {
        MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'TAKEN-CLAVE', 'nombre' => 'Clave ocupada'])
        ));

        $source = MetIndicator::unguarded(fn () => MetIndicator::create(
            $this->minimalIndicatorAttributes(['clave' => 'DUP-REACT-SRC', 'nombre' => 'Fuente reactiva'])
        ));

        $this->catalogComponentTest()
            ->call('openDuplicateModal', $source->id)
            ->set('duplicateClave', 'TAKEN-CLAVE')
            ->assertSet('duplicateClaveExists', true)
            ->assertSet('duplicateClaveError', 'El código "TAKEN-CLAVE" ya está en uso.');

        // Al limpiar, debe resetear
        $this->catalogComponentTest()
            ->call('openDuplicateModal', $source->id)
            ->set('duplicateClave', 'FREE-CLAVE')
            ->assertSet('duplicateClaveExists', false)
            ->assertSet('duplicateClaveError', '');
    }
}
