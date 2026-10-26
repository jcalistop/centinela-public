<?php

namespace Tests\Unit\Policies;

use App\Models\CenHieDependency;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Policies\DocumentPolicy;
use App\Support\Voyager\DocumentBread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class DocumentPolicyTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private DocumentPolicy $policy;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DocumentPolicy;
        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(function (): void {
            CenHieDependency::create([
                'id' => 1,
                'codigo' => 'SS-DOC-1',
                'nombre' => 'Servicio documentos 1',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]);
            CenHieDependency::create([
                'id' => 2,
                'codigo' => 'SS-DOC-2',
                'nombre' => 'Servicio documentos 2',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]);
        });

        $this->seedMinimalDocumentsBreadForEmbeddedBrowse();
    }

    public function test_admin_can_add_edit_and_delete_without_voyager_permissions(): void
    {
        $admin = $this->userForRole('admin', 1);

        $this->assertFalse($this->policy->browse($admin));
        $this->assertFalse($this->policy->read($admin));
        $this->assertTrue($this->policy->add($admin));
        $this->assertTrue($this->policy->edit($admin));
        $this->assertTrue($this->policy->delete($admin));
    }

    public function test_admin_with_read_voyager_permission_can_read(): void
    {
        $admin = $this->userForRole('admin', 1);
        $readPermission = Permission::firstOrCreate(
            ['key' => 'read_'.DocumentBread::permissionTableName()],
            ['table_name' => DocumentBread::permissionTableName()]
        );
        $admin->role->permissions()->sync([$readPermission->id]);
        $admin->refresh()->loadMissing('role.permissions');

        $this->assertTrue($this->policy->read($admin, $this->documentForService(1)));
    }

    public function test_control_gestion_can_add_edit_and_delete_without_voyager_permissions(): void
    {
        $controlGestion = $this->userForRole('control-gestion', 1);

        $this->assertTrue($this->policy->add($controlGestion));
        $this->assertTrue($this->policy->edit($controlGestion));
        $this->assertTrue($this->policy->delete($controlGestion));
    }

    public function test_control_gestion_cannot_edit_document_from_other_service(): void
    {
        $controlGestion = $this->userForRole('control-gestion', 1);
        $document = $this->documentForService(2);

        $this->assertFalse($this->policy->edit($controlGestion, $document));
        $this->assertFalse($this->policy->delete($controlGestion, $document));
    }

    public function test_admin_can_edit_document_from_any_service(): void
    {
        $admin = $this->userForRole('admin', 1);
        $document = $this->documentForService(2);

        $this->assertTrue($this->policy->edit($admin, $document));
        $this->assertTrue($this->policy->delete($admin, $document));
    }

    public function test_visualizador_with_read_voyager_permission_can_read_but_not_write(): void
    {
        $visualizador = $this->userForRole('visualizador', 1);
        $readPermission = Permission::firstOrCreate(
            ['key' => 'read_'.DocumentBread::permissionTableName()],
            ['table_name' => DocumentBread::permissionTableName()]
        );
        $visualizador->role->permissions()->sync([$readPermission->id]);
        $visualizador->refresh()->loadMissing('role.permissions');

        $document = $this->documentForService(1);

        $this->assertTrue($this->policy->read($visualizador, $document));
        $this->assertFalse($this->policy->browse($visualizador, $document));
        $this->assertFalse($this->policy->add($visualizador));
        $this->assertFalse($this->policy->edit($visualizador, $document));
        $this->assertFalse($this->policy->delete($visualizador, $document));
    }

    public function test_visualizador_without_voyager_permissions_cannot_write(): void
    {
        $visualizador = $this->userForRole('visualizador', 1);

        $this->assertFalse($this->policy->read($visualizador));
        $this->assertFalse($this->policy->browse($visualizador));
        $this->assertFalse($this->policy->add($visualizador));
        $this->assertFalse($this->policy->edit($visualizador));
        $this->assertFalse($this->policy->delete($visualizador));
    }

    private function userForRole(string $roleName, int $dependencyId): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $dependencyId,
        ]);
        $user->role()->associate($role)->save();

        return $user->fresh()->loadMissing('role');
    }

    private function documentForService(int $dependencyId): Document
    {
        $document = new Document;
        $document->exists = true;
        $document->setAttribute($document->getKeyName(), 1);
        $document->setAttribute('nombre', 'Documento de prueba');
        $document->setAttribute('year_id', $this->year->id);
        $document->setAttribute('cen_hie_dependency_id', $dependencyId);
        $document->setAttribute('user_id', 1);

        return $document;
    }
}
