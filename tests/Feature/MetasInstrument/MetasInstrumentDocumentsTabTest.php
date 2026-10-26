<?php

namespace Tests\Feature\MetasInstrument;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TCG\Voyager\Models\DataType;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class MetasInstrumentDocumentsTabTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private Year $year;

    private User $adminUser;

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

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->grantDocumentBreadPermissionsToRole($adminRole);

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'CERRADO',
            'habilitado' => 1,
        ]));

        DataType::unguarded(fn () => DataType::create([
            'name' => 'indicator_types',
            'slug' => 'instrumentos-evaluacion',
            'display_name_singular' => 'Tipo de indicador',
            'display_name_plural' => 'Tipos de indicador',
            'model_name' => IndicatorType::class,
            'generate_permissions' => 0,
            'server_side' => 0,
        ]));

        $this->seedMinimalDocumentsBreadForEmbeddedBrowse();
    }

    /** @test */
    public function metas_instrument_index_includes_documents_tab_markup(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/metas-18834');

        $response->assertOk();
        $response->assertSee('href="#tab-documentos"', false);
        $response->assertSee('Documentos', false);
        $response->assertSee('Documentos — 2026', false);
        $response->assertSee('Agregar documento', false);
    }
}
