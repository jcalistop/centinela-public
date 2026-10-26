<?php

namespace Tests\Feature\MetasInstrument;

use App\Livewire\MetasInstrument\IndicatorCatalogComponent;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MetasInstrumentIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function catalogo_solo_lista_indicadores_del_instrumento_activo(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($adminRole)->save();

        $typeA = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $typeB = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-27777',
            'nombre_indicador' => 'Metas instrumento B (test aislamiento)',
            'descripcion_indicador' => 'B',
            'icon_class' => 'voyager-book',
            'route' => 'metas-27777',
            'view' => null,
            'estado' => 1,
            'orden' => 2,
        ]));

        foreach ([$typeA, $typeB] as $type) {
            IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
                'indicator_type_id' => $type->id,
                'year_id' => $year->id,
                'cen_hie_dependency_id' => 1,
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => 1,
            ]));
        }

        $indA = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $typeA->id,
            'clave' => 'IND-A',
            'nombre' => 'Indicador Ley 18834',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $year->id,
            'estado' => true,
        ]));

        $indB = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $typeB->id,
            'clave' => 'IND-B',
            'nombre' => 'Indicador Ley 19664',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $year->id,
            'estado' => true,
        ]));

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        Livewire::test(IndicatorCatalogComponent::class, ['indicatorTypeId' => $typeA->id])
            ->assertSee($indA->nombre)
            ->assertDontSee($indB->nombre);

        Livewire::test(IndicatorCatalogComponent::class, ['indicatorTypeId' => $typeB->id])
            ->assertSee($indB->nombre)
            ->assertDontSee($indA->nombre);
    }
}
