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

class Metas19664IsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Catálogo filtrado por indicator_type_id del instrumento 19.664 (fila sembrada en migración),
     * sin mezclar indicadores del tipo 18.834. Se prueba vía Livewire porque el layout Voyager del
     * índice HTTP requiere DataTypes que no existen en BD de test (ver MetasInstrumentControllerTest).
     *
     * @test
     */
    public function catalogo_metas_19664_no_lista_indicadores_del_tipo_18834(): void
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

        $type18834 = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $type19664 = IndicatorType::query()->where('route', 'metas-19664')->firstOrFail();

        foreach ([$type18834, $type19664] as $type) {
            IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
                'indicator_type_id' => $type->id,
                'year_id' => $year->id,
                'cen_hie_dependency_id' => 1,
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => 1,
            ]));
        }

        MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type18834->id,
            'clave' => 'SDD018-18834-ONLY',
            'nombre' => 'Indicador exclusivo 18.834 SDD018',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $year->id,
            'estado' => true,
        ]));

        MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type19664->id,
            'clave' => 'SDD018-19664-ONLY',
            'nombre' => 'Indicador exclusivo 19.664 SDD018',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $year->id,
            'estado' => true,
        ]));

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        Livewire::test(IndicatorCatalogComponent::class, ['indicatorTypeId' => $type19664->id])
            ->assertSee('Indicador exclusivo 19.664 SDD018')
            ->assertDontSee('Indicador exclusivo 18.834 SDD018');

        Livewire::test(IndicatorCatalogComponent::class, ['indicatorTypeId' => $type18834->id])
            ->assertSee('Indicador exclusivo 18.834 SDD018')
            ->assertDontSee('Indicador exclusivo 19.664 SDD018');
    }

    /** @test */
    public function plantilla_excel_usa_prefijo_metas19664_para_ruta_metas_19664(): void
    {
        if (! is_dir(storage_path('temp'))) {
            mkdir(storage_path('temp'), 0755, true);
        }

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

        $type19664 = IndicatorType::query()->where('route', 'metas-19664')->firstOrFail();

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type19664->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type19664->id,
            'clave' => 'SDD018-XLSX',
            'nombre' => 'Indicador plantilla SDD018',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $year->id,
            'estado' => true,
        ]));

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $response = $this->get('/admin/metas-19664/indicador/'.$indicator->id.'/plantilla');

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('Metas19664', (string) $disposition);
        $this->assertStringContainsString('SDD018-XLSX', (string) $disposition);
    }
}
