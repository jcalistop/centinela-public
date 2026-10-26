<?php

namespace Tests\Feature\Voyager;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetIndicatorReferente;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class VoyagerUserControlGestionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $cgRole;

    private Role $referenteRole;

    private Role $parametrizadorRole;

    private User $controlGestionUser;

    private User $adminTarget;

    private User $referenteTarget;

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

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Servicio de Salud Dos',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $this->parametrizadorRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);

        $this->attachUserBreadPermissions($this->cgRole, [
            'browse_admin',
            'browse_users',
            'read_users',
            'add_users',
            'edit_users',
        ]);

        $this->attachUserBreadPermissions($this->adminRole, [
            'browse_admin',
            'browse_users',
            'read_users',
            'add_users',
            'edit_users',
        ]);

        $this->ensureUsersDataType();

        $this->controlGestionUser = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'cg-auth@test.cl',
        ]);

        $this->adminTarget = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'admin-target@test.cl',
        ]);

        $this->referenteTarget = User::factory()->create([
            'role_id' => $this->referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente-target@test.cl',
        ]);
    }

    public function test_control_gestion_browse_lists_admin_same_ss(): void
    {
        $this->actingAs($this->controlGestionUser)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.index'))
            ->assertOk()
            ->assertSee($this->adminTarget->email, false);
    }

    public function test_control_gestion_get_edit_admin_same_ss_returns_forbidden(): void
    {
        $this->actingAs($this->controlGestionUser)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.edit', $this->adminTarget->getKey()))
            ->assertForbidden();
    }

    public function test_control_gestion_patch_admin_same_ss_returns_forbidden(): void
    {
        $payload = $this->validUpdatePayload($this->adminTarget);

        $this->actingAs($this->controlGestionUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->adminTarget->getKey()), $payload)
            ->assertForbidden();
    }

    public function test_control_gestion_patch_parametrizador_same_ss_returns_forbidden(): void
    {
        $parametrizador = User::factory()->create([
            'role_id' => $this->parametrizadorRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'param-target@test.cl',
        ]);

        $this->actingAs($this->controlGestionUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $parametrizador->getKey()), $this->validUpdatePayload($parametrizador))
            ->assertForbidden();
    }

    public function test_control_gestion_store_with_admin_role_returns_unprocessable(): void
    {
        $payload = $this->validStorePayload([
            'email' => 'nuevo.admin@test.cl',
            'role_id' => (string) $this->adminRole->id,
        ]);

        $this->actingAs($this->controlGestionUser)
            ->withSession(['indicator_year' => $this->year])
            ->post(route('voyager.users.store'), $payload)
            ->assertSessionHasErrors('role_id');
    }

    public function test_control_gestion_can_update_referente_same_ss(): void
    {
        $payload = $this->validUpdatePayload($this->referenteTarget, [
            'name' => 'Referente Actualizado',
        ]);

        $this->actingAs($this->controlGestionUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->referenteTarget->getKey()), $payload)
            ->assertRedirect();

        $this->assertSame('Referente Actualizado', $this->referenteTarget->fresh()->name);
    }

    public function test_control_gestion_cannot_update_user_from_other_ss(): void
    {
        $otherSsReferente = User::factory()->create([
            'role_id' => $this->referenteRole->id,
            'cen_hie_dependency_id' => 2,
            'email' => 'referente-otro-ss@test.cl',
        ]);

        $this->actingAs($this->controlGestionUser)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $otherSsReferente->getKey()), $this->validUpdatePayload($otherSsReferente))
            ->assertForbidden();
    }

    public function test_admin_can_update_admin_user(): void
    {
        $adminActor = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'admin-actor@test.cl',
        ]);

        $payload = $this->validUpdatePayload($this->adminTarget, [
            'name' => 'Admin Renombrado',
        ]);

        $this->actingAs($adminActor)
            ->withSession(['indicator_year' => $this->year])
            ->put(route('voyager.users.update', $this->adminTarget->getKey()), $payload)
            ->assertRedirect();

        $this->assertSame('Admin Renombrado', $this->adminTarget->fresh()->name);
    }

    public function test_users_show_page_renders_successfully(): void
    {
        $adminActor = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'admin-show@test.cl',
        ]);

        $this->actingAs($adminActor)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.show', $this->adminTarget->getKey()))
            ->assertOk()
            ->assertSee($this->adminTarget->email, false);
    }

    public function test_users_show_page_lists_referent_indicators(): void
    {
        $indicatorType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas18834',
            'nombre_indicador' => 'Metas Ley 18.834',
            'route' => 'metas-18834',
            'estado' => true,
            'orden' => 1,
        ]));

        $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'clave' => 'PERF-01',
            'nombre' => 'Indicador perfil referente',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $metIndicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referenteTarget->id,
        ]));

        $adminActor = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'admin-referentes@test.cl',
        ]);

        $this->actingAs($adminActor)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.show', $this->referenteTarget->getKey()))
            ->assertOk()
            ->assertSee('Indicadores como referente', false)
            ->assertSee('PERF-01', false)
            ->assertSee('Indicador perfil referente', false)
            ->assertSee('Metas Ley 18.834', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo.cg@test.cl',
            'password' => 'claveSegura1',
            'role_id' => (string) $this->referenteRole->id,
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
            if ($existing instanceof DataType) {
                $existing->update([
                    'controller' => '\\App\\Http\\Controllers\\Voyager\\CentinelaVoyagerUserController',
                ]);
                $existing->scope = 'voyagerActorManageable';
                $existing->save();
                $dataTypeId = (int) $existing->getKey();
            } else {
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
                $dataType->scope = 'voyagerActorManageable';
                $dataType->save();
                $dataTypeId = (int) $dataType->getKey();
            }

            if (! Schema::hasTable('data_rows')) {
                return;
            }

            $rows = [
                ['field' => 'name', 'type' => 'text', 'display_name' => 'Nombre', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 1],
                ['field' => 'email', 'type' => 'text', 'display_name' => 'Email', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 2],
                ['field' => 'password', 'type' => 'password', 'display_name' => 'Password', 'required' => 1, 'browse' => 0, 'read' => 0, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 3],
                ['field' => 'role_id', 'type' => 'text', 'display_name' => 'Rol', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 4],
                ['field' => 'cen_hie_dependency_id', 'type' => 'text', 'display_name' => 'Servicio de Salud', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 5],
            ];

            foreach ($rows as $row) {
                DataRow::query()->updateOrInsert(
                    ['data_type_id' => $dataTypeId, 'field' => $row['field']],
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
