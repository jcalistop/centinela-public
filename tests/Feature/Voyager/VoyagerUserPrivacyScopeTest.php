<?php

namespace Tests\Feature\Voyager;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Policies\CustomUserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

/**
 * SDD-175: privacidad inter-SS en browse/read de usuarios Voyager.
 */
class VoyagerUserPrivacyScopeTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $cgRole;

    private Role $referenteRole;

    private User $adminUser;

    private User $controlGestionUser;

    private User $referenteUser;

    private User $sameSsReferente;

    private User $otherSsReferente;

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

        foreach ([$this->adminRole, $this->cgRole, $this->referenteRole] as $role) {
            $this->attachUserBreadPermissions($role, [
                'browse_admin',
                'browse_users',
                'read_users',
            ]);
        }

        $this->attachUserBreadPermissions($this->adminRole, ['add_users', 'edit_users']);
        $this->attachUserBreadPermissions($this->cgRole, ['add_users', 'edit_users']);

        $this->ensureUsersDataType();

        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'admin-privacy@test.cl',
            'name' => 'Admin Privacy',
        ]);

        $this->controlGestionUser = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'cg-privacy@test.cl',
            'name' => 'CG Privacy',
        ]);

        $this->referenteUser = User::factory()->create([
            'role_id' => $this->referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente-actor@test.cl',
            'name' => 'Referente Actor',
        ]);

        $this->sameSsReferente = User::factory()->create([
            'role_id' => $this->referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente-mismo-ss@test.cl',
            'name' => 'Referente Mismo SS',
        ]);

        $this->otherSsReferente = User::factory()->create([
            'role_id' => $this->referenteRole->id,
            'cen_hie_dependency_id' => 2,
            'email' => 'referente-otro-ss@test.cl',
            'name' => 'Referente Otro SS',
        ]);
    }

    public function test_referente_browse_only_sees_same_ss_users(): void
    {
        $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.index'))
            ->assertOk()
            ->assertSee($this->sameSsReferente->email, false)
            ->assertSee($this->controlGestionUser->email, false)
            ->assertDontSee($this->otherSsReferente->email, false);
    }

    public function test_referente_can_read_same_ss_user(): void
    {
        $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.show', $this->sameSsReferente->getKey()))
            ->assertOk()
            ->assertSee($this->sameSsReferente->email, false);
    }

    public function test_referente_cannot_read_other_ss_user(): void
    {
        $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.show', $this->otherSsReferente->getKey()))
            ->assertNotFound();
    }

    public function test_admin_browse_sees_all_ss_users(): void
    {
        $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.index'))
            ->assertOk()
            ->assertSee($this->sameSsReferente->email, false)
            ->assertSee($this->otherSsReferente->email, false);
    }

    public function test_control_gestion_browse_only_sees_same_ss_users(): void
    {
        $this->actingAs($this->controlGestionUser)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.index'))
            ->assertOk()
            ->assertSee($this->sameSsReferente->email, false)
            ->assertDontSee($this->otherSsReferente->email, false);
    }

    public function test_actor_without_ss_scope_returns_empty(): void
    {
        $orphaned = User::factory()->create([
            'role_id' => $this->referenteRole->id,
            'cen_hie_dependency_id' => 1,
            'email' => 'referente-sin-ss@test.cl',
            'name' => 'Referente Sin SS',
        ]);

        // Columna NOT NULL en BD; se prueba fail-closed del scope con SS nulo en memoria.
        $orphaned->setAttribute('cen_hie_dependency_id', null);
        $this->actingAs($orphaned);

        $this->assertSame(0, User::query()->voyagerActorManageable()->count());
    }

    public function test_browse_forbidden_when_users_bread_scope_missing(): void
    {
        $dataType = DataType::query()->where('slug', 'users')->first();
        $this->assertInstanceOf(DataType::class, $dataType);
        $dataType->scope = null;
        $dataType->save();

        $this->actingAs($this->referenteUser)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('voyager.users.index'))
            ->assertForbidden();
    }

    public function test_scope_voyager_actor_manageable_filters_for_referente(): void
    {
        $this->actingAs($this->referenteUser);

        $emails = User::query()
            ->voyagerActorManageable()
            ->pluck('email')
            ->all();

        $this->assertContains($this->sameSsReferente->email, $emails);
        $this->assertNotContains($this->otherSsReferente->email, $emails);
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
                    'policy_name' => CustomUserPolicy::class,
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
                    'policy_name' => CustomUserPolicy::class,
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
