<?php

namespace Tests\Feature\Documents;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Support\Voyager\DocumentBread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class DocumentCrudTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private Year $year;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS-DOC-TAB',
            'nombre' => 'Servicio documentos CRUD test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->adminUser->role()->associate($adminRole)->save();
        $this->grantBrowseAdminPermissionToRole($adminRole);
        $this->grantDocumentBreadPermissionsToRole($adminRole);

        $this->seedMinimalDocumentsBreadForEmbeddedBrowse();

        $this->adminUser->refresh()->loadMissing('role');
        $this->assertTrue(
            $this->adminUser->hasPermission('browse_admin'),
            'VoyagerAdminMiddleware requiere browse_admin.'
        );
        $this->assertTrue(
            $this->adminUser->hasPermission('add_'.DocumentBread::permissionTableName()),
            'Permiso add_'.DocumentBread::permissionTableName().' requerido para el formulario.'
        );
    }

    /** @test */
    public function create_form_prefills_hub_and_indicator_context_for_adp(): void
    {
        /** @var IndicatorType $indicatorType */
        $indicatorType = IndicatorType::query()->where('clave_indicador', 'adp')->firstOrFail();

        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get($this->documentsCreatePath([
                'hub' => 'adp',
                'indicator_type_id' => $indicatorType->getKey(),
            ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('name="centinela_document_hub"', $content);
        $this->assertStringContainsString('value="adp"', $content);
        $this->assertStringContainsString('Alta desde el hub del instrumento', $content);
    }

    /** @test */
    public function create_form_carries_metas_hub_context_from_metas_route(): void
    {
        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get($this->documentsCreatePath([
                'hub' => 'metas',
                'metas_route' => 'metas-18834',
            ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('name="centinela_document_hub"', $content);
        $this->assertStringContainsString('value="metas"', $content);
        $this->assertStringContainsString('name="centinela_metas_route"', $content);
        $this->assertStringContainsString('value="metas-18834"', $content);
        $this->assertStringContainsString((string) $indicatorType->getKey(), $content);
    }

    /** @test */
    public function show_view_links_back_to_metas_documents_hub_for_instrument_document(): void
    {
        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $documentId = DB::table('documents')->insertGetId([
            'nombre' => 'Doc visible',
            'descripcion' => '',
            'autor' => '',
            'file' => '',
            'year_id' => $this->year->id,
            'indicator_type_id' => $indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/'.DocumentBread::slug().'/'.$documentId);

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Volver a Documentos del instrumento', $content);
        $this->assertStringContainsString(
            route('metas.instrument.index', ['indicatorType' => $indicatorType]).'#tab-documentos',
            $content
        );
        $this->assertStringContainsString('hub=metas', $content);
        $this->assertStringContainsString('metas_route=metas-18834', $content);
    }

    /** @test */
    public function edit_form_carries_hub_context_from_document_indicator_type(): void
    {
        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $documentId = DB::table('documents')->insertGetId([
            'nombre' => 'Doc editable',
            'descripcion' => '',
            'autor' => '',
            'file' => '',
            'year_id' => $this->year->id,
            'indicator_type_id' => $indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/'.DocumentBread::slug().'/'.$documentId.'/edit');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('name="centinela_document_hub"', $content);
        $this->assertStringContainsString('value="metas"', $content);
        $this->assertStringContainsString('name="centinela_metas_route"', $content);
        $this->assertStringContainsString('value="metas-18834"', $content);
        $this->assertStringContainsString('Estás editando un documento del instrumento', $content);
    }

    /** @test */
    public function update_redirects_to_metas_documents_tab_for_instrument_document(): void
    {
        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $documentId = DB::table('documents')->insertGetId([
            'nombre' => 'Doc actualizable',
            'descripcion' => '',
            'autor' => '',
            'file' => '',
            'year_id' => $this->year->id,
            'indicator_type_id' => $indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->put('/admin/'.DocumentBread::slug().'/'.$documentId, [
                'nombre' => 'Doc actualizado',
            ]);

        $response->assertRedirect(
            route('metas.instrument.index', ['indicatorType' => $indicatorType]).'#tab-documentos'
        );
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'nombre' => 'Doc actualizado',
        ]);
    }

    /** @test */
    public function destroy_redirects_to_metas_documents_tab_for_instrument_document(): void
    {
        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $documentId = DB::table('documents')->insertGetId([
            'nombre' => 'Doc eliminable',
            'descripcion' => '',
            'autor' => '',
            'file' => '',
            'year_id' => $this->year->id,
            'indicator_type_id' => $indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->delete('/admin/'.DocumentBread::slug().'/'.$documentId);

        $response->assertRedirect(
            route('metas.instrument.index', ['indicatorType' => $indicatorType]).'#tab-documentos'
        );
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
    }

    /** @test */
    public function destroy_redirects_to_hub_from_hidden_fields_when_document_has_no_indicator_type(): void
    {
        $documentId = DB::table('documents')->insertGetId([
            'nombre' => 'Doc sin instrumento',
            'descripcion' => '',
            'autor' => '',
            'file' => '',
            'year_id' => $this->year->id,
            'indicator_type_id' => 0,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->delete('/admin/'.DocumentBread::slug().'/'.$documentId, [
                'centinela_document_hub' => 'comges',
            ]);

        $target = $response->headers->get('Location');
        $this->assertIsString($target);
        $this->assertStringContainsString('/admin/compromisos', $target);
        $this->assertStringContainsString('#documentos', $target);
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
    }

    /** @test */
    public function invalid_hub_query_does_not_add_hidden_hub_fields(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get($this->documentsCreatePath([
                'hub' => 'no-existe',
            ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('name="centinela_document_hub"', $content);
        $this->assertStringNotContainsString('Alta desde el hub del instrumento', $content);
    }

    /**
     * Ruta relativa: evitar {@see route()} con APP_URL externo durante HTTP tests (redirect canónico).
     *
     * @param  array<string, scalar>  $query
     */
    private function documentsCreatePath(array $query = []): string
    {
        $path = '/admin/'.DocumentBread::slug().'/create';

        return $query === [] ? $path : $path.'?'.http_build_query($query);
    }
}
