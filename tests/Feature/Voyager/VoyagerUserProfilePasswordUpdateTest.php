<?php

namespace Tests\Feature\Voyager;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class VoyagerUserProfilePasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $referenteRole;

    private User $adminUser;

    private User $referenteUser;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Uno',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->attachUserBreadPermissions($this->adminRole, [
            'browse_admin',
            'browse_users',
            'read_users',
            'edit_users',
        ]);

        $this->attachUserBreadPermissions($this->referenteRole, [
            'browse_admin',
        ]);

        $this->ensureUsersDataType();

        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'admin-profile-pwd@test.cl',
        ]);

        $this->referenteUser = User::factory()->create([
            'role_id' => $this->referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente-profile-pwd@test.cl',
            'password' => 'claveAnterior1',
        ]);
    }

    public function test_referente_self_update_with_password_redirects_to_dashboard_without_loop(): void
    {
        $payload = $this->validUpdatePayload($this->referenteUser, [
            'password' => 'claveNueva88',
        ]);

        $response = $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->referenteUser->getKey()), $payload);

        $response->assertRedirect(route('voyager.dashboard'));
        $response->assertSessionHas('message');
        $this->assertAuthenticatedAs($this->referenteUser->fresh());
        $this->assertTrue(Hash::check('claveNueva88', $this->referenteUser->fresh()->password));
    }

    public function test_referente_self_update_with_password_keeps_role_and_admin_access(): void
    {
        $payload = $this->validUpdatePayload($this->referenteUser, [
            'password' => 'claveNueva88',
        ]);

        $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->referenteUser->getKey()), $payload);

        $fresh = $this->referenteUser->fresh();
        $this->assertNotNull($fresh->role_id, 'El usuario no debe perder su rol al cambiar la contraseña en su perfil.');
        $this->assertSame((int) $this->referenteRole->id, (int) $fresh->role_id);
        $this->assertTrue($fresh->hasPermission('browse_admin'), 'El usuario debe conservar browse_admin tras editar su perfil (evita ERR_TOO_MANY_REDIRECTS).');
    }

    public function test_referente_self_update_without_role_in_request_keeps_role(): void
    {
        $payload = [
            'name' => $this->referenteUser->name,
            'email' => $this->referenteUser->email,
            'cen_hie_dependency_id' => (string) $this->referenteUser->cen_hie_dependency_id,
            'password' => 'claveNueva88',
        ];

        $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->referenteUser->getKey()), $payload);

        $fresh = $this->referenteUser->fresh();
        $this->assertSame((int) $this->referenteRole->id, (int) $fresh->role_id);
        $this->assertTrue($fresh->hasPermission('browse_admin'));
    }

    public function test_referente_self_update_without_password_redirects_to_profile(): void
    {
        $payload = $this->validUpdatePayload($this->referenteUser, [
            'name' => 'Referente Actualizado',
        ]);

        $response = $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->referenteUser->getKey()), $payload);

        $response->assertRedirect(route('voyager.profile'));
        $response->assertSessionHas('message');

        $this->assertSame('Referente Actualizado', $this->referenteUser->fresh()->name);
        $this->assertTrue(Hash::check('claveAnterior1', $this->referenteUser->fresh()->password));
    }

    public function test_referente_self_update_with_short_password_returns_validation_error(): void
    {
        $payload = $this->validUpdatePayload($this->referenteUser, [
            'password' => 'abc',
        ]);

        $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->referenteUser->getKey()), $payload)
            ->assertSessionHasErrors('password');
    }

    public function test_admin_update_other_user_with_password_still_redirects_to_users_show(): void
    {
        $payload = $this->validUpdatePayload($this->referenteUser, [
            'password' => 'claveAdminSet1',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->referenteUser->getKey()), $payload);

        $response->assertRedirect(route('voyager.users.show', ['id' => $this->referenteUser->getKey()]));
        $this->assertTrue(Hash::check('claveAdminSet1', $this->referenteUser->fresh()->password));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUpdatePayload(User $target, array $overrides = []): array
    {
        return array_merge([
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => (string) $target->role_id,
            'cen_hie_dependency_id' => (string) $target->cen_hie_dependency_id,
        ], $overrides);
    }

    /**
     * @param  list<string>  $keys
     */
    private function attachUserBreadPermissions(Role $role, array $keys): void
    {
        $permissionIds = [];
        foreach ($keys as $key) {
            $tableName = $key === 'browse_admin' ? null : 'users';
            $permission = Permission::firstOrCreate(
                ['key' => $key, 'table_name' => $tableName],
                ['key' => $key, 'table_name' => $tableName],
            );
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function ensureUsersDataType(): void
    {
        DataType::unguarded(function (): void {
            $existing = DataType::query()->where('slug', 'users')->first();
            $dataTypeId = null;
            if ($existing instanceof DataType) {
                $existing->update([
                    'controller' => '\\App\\Http\\Controllers\\Voyager\\CentinelaVoyagerUserController',
                ]);

                $dataTypeId = (int) $existing->getKey();
            }

            if ($dataTypeId === null) {
                $dataType = DataType::create([
                    'name' => 'users',
                    'slug' => 'users',
                    'display_name_singular' => 'Usuario',
                    'display_name_plural' => 'Usuarios',
                    'icon' => 'voyager-person',
                    'model_name' => User::class,
                    'controller' => '\\App\\Http\\Controllers\\Voyager\\CentinelaVoyagerUserController',
                    'generate_permissions' => 1,
                    'server_side' => 0,
                ]);

                $dataTypeId = (int) $dataType->getKey();
            }

            if (! Schema::hasTable('data_rows')) {
                return;
            }

            $this->ensureUsersBreadDataRows($dataTypeId);
        });
    }

    private function ensureUsersBreadDataRows(int $dataTypeId): void
    {
        DataRow::unguarded(function () use ($dataTypeId): void {
            $rows = [
                ['field' => 'name', 'type' => 'text', 'display_name' => 'Nombre', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 1],
                ['field' => 'email', 'type' => 'text', 'display_name' => 'Email', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 2],
                ['field' => 'password', 'type' => 'password', 'display_name' => 'Password', 'required' => 1, 'browse' => 0, 'read' => 0, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 3],
                ['field' => 'role_id', 'type' => 'text', 'display_name' => 'Rol', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 4],
                ['field' => 'cen_hie_dependency_id', 'type' => 'text', 'display_name' => 'Servicio de Salud', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 5],
            ];

            foreach ($rows as $row) {
                DataRow::query()->updateOrInsert(
                    [
                        'data_type_id' => $dataTypeId,
                        'field' => $row['field'],
                    ],
                    [
                        'type' => $row['type'],
                        'display_name' => $row['display_name'],
                        'required' => $row['required'],
                        'browse' => $row['browse'],
                        'read' => $row['read'],
                        'edit' => $row['edit'],
                        'add' => $row['add'],
                        'delete' => $row['delete'],
                        'details' => $row['details'],
                        'order' => $row['order'],
                    ],
                );
            }
        });
    }
}
