<?php

namespace Tests\Feature\Voyager;

use App\Mail\UserCredentialsMail;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class VoyagerUserCredentialsEmailTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $viewerRole;

    private User $userAdmin;

    private User $viewerUser;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Uno',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->viewerRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);

        $this->attachUserBreadPermissions($this->adminRole, [
            'browse_admin',
            'browse_users',
            'read_users',
            'add_users',
            'edit_users',
        ]);

        $this->attachUserBreadPermissions($this->viewerRole, [
            'browse_admin',
            'browse_users',
            'read_users',
            'edit_users',
        ]);

        $this->ensureUsersDataType();

        $this->userAdmin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'admin-credentials@test.cl',
        ]);

        $this->viewerUser = User::factory()->create([
            'role_id' => $this->viewerRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'viewer-credentials@test.cl',
        ]);
    }

    public function test_store_with_toggle_on_queues_credentials_email(): void
    {
        $payload = $this->validStorePayload([
            'email' => 'nuevo.usuario@test.cl',
            'send_credentials_email' => '1',
            'password' => 'claveSegura1',
        ]);

        $response = $this->actingAs($this->userAdmin)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('voyager.users.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('message');
        $this->assertStringContainsString(
            'credenciales',
            (string) $response->getSession()->get('message'),
        );
        $response->assertSessionHas('alert-type', 'success');

        Mail::assertQueued(UserCredentialsMail::class, function (UserCredentialsMail $mail): bool {
            return $mail->hasTo('nuevo.usuario@test.cl')
                && $mail->plainPassword === 'claveSegura1'
                && $mail->isUpdate === false;
        });
    }

    public function test_store_without_toggle_does_not_queue_email(): void
    {
        $payload = $this->validStorePayload([
            'email' => 'sin.correo@test.cl',
            'password' => 'claveSegura1',
        ]);

        $this->actingAs($this->userAdmin)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('voyager.users.store'), $payload)
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_control_gestion_store_assigns_servicio_salud_without_form_field(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->attachUserBreadPermissions($cgRole, [
            'browse_admin',
            'browse_users',
            'read_users',
            'add_users',
            'edit_users',
        ]);

        $controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'cg@test.cl',
        ]);

        $payload = [
            'name' => 'Usuario CG',
            'email' => 'usuario.cg@test.cl',
            'password' => 'claveSegura1',
            'role_id' => (string) $this->viewerRole->id,
        ];

        $this->actingAs($controlGestion)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('voyager.users.store'), $payload)
            ->assertRedirect();

        $created = User::query()->where('email', 'usuario.cg@test.cl')->first();
        $this->assertInstanceOf(User::class, $created);
        $this->assertSame(1, $created->cen_hie_dependency_id);
    }

    public function test_update_with_toggle_on_and_password_queues_credentials_email(): void
    {
        $target = User::factory()->create([
            'role_id' => $this->viewerRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'objetivo@test.cl',
        ]);

        $payload = $this->validUpdatePayload($target, [
            'password' => 'nuevaClave88',
            'send_credentials_email' => '1',
        ]);

        $this->actingAs($this->userAdmin)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $target->getKey()), $payload)
            ->assertRedirect();

        Mail::assertQueued(UserCredentialsMail::class, function (UserCredentialsMail $mail): bool {
            return $mail->hasTo('objetivo@test.cl')
                && $mail->plainPassword === 'nuevaClave88'
                && $mail->isUpdate === true;
        });
    }

    public function test_update_with_toggle_on_without_password_returns_validation_error(): void
    {
        $target = User::factory()->create([
            'role_id' => $this->viewerRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $payload = $this->validUpdatePayload($target, [
            'password' => '',
            'send_credentials_email' => '1',
        ]);

        $this->actingAs($this->userAdmin)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $target->getKey()), $payload)
            ->assertSessionHasErrors('password');

        Mail::assertNothingQueued();
    }

    public function test_self_profile_update_with_forced_toggle_does_not_queue_email(): void
    {
        $payload = $this->validUpdatePayload($this->viewerUser, [
            'password' => 'claveSegura1',
            'send_credentials_email' => '1',
        ]);

        $this->actingAs($this->viewerUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->viewerUser->getKey()), $payload)
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo@test.cl',
            'password' => 'claveSegura1',
            'role_id' => (string) $this->viewerRole->id,
            'cen_hie_dependency_id' => '1',
        ], $overrides);
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
                // Mínimo requerido para insertar sin caer en email NULL.
                ['field' => 'name', 'type' => 'text', 'display_name' => 'Nombre', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 1],
                ['field' => 'email', 'type' => 'text', 'display_name' => 'Email', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 2],
                ['field' => 'password', 'type' => 'password', 'display_name' => 'Password', 'required' => 1, 'browse' => 0, 'read' => 0, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 3],
                // Se persiste mínimo; luego el controlador puede sincronizar belongsTo.
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
