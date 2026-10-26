<?php

namespace Tests\Feature;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class AdpInstrumentDocumentsTabTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private Year $year;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        IndicatorType::query()->where('clave_indicador', 'adp')->firstOrFail();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS-ADP-TAB',
            'nombre' => 'Servicio ADP tab test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->grantDocumentBreadPermissionsToRole($adminRole);
        $this->seedMinimalDocumentsBreadForEmbeddedBrowse();
    }

    /** @test */
    public function adp_hub_includes_documents_tab_and_embedded_browse(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/adp');

        $response->assertOk();
        $response->assertSee('href="#tab-documentos"', false);
        $response->assertSee('Documentación normativa', false);
        $response->assertSee('No existen registros', false);
    }

    /** @test */
    public function adp_documents_tab_shows_add_button_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/adp');

        $response->assertOk();
        $response->assertSee('Agregar documento', false);
    }
}
