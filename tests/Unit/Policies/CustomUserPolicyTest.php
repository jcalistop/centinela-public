<?php

namespace Tests\Unit\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\CustomUserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class CustomUserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CustomUserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CustomUserPolicy;
    }

    public function test_control_gestion_cannot_edit_roles_on_admin(): void
    {
        $cg = $this->userWithRoleAndPermission('control-gestion', 'edit_users', 1);
        $admin = $this->userWithRole('admin', 1);

        $this->assertFalse($this->policy->editRoles($cg, $admin));
        $this->assertFalse($this->policy->edit($cg, $admin));
    }

    public function test_control_gestion_can_edit_roles_on_referente_same_ss(): void
    {
        $cg = $this->userWithRoleAndPermission('control-gestion', 'edit_users', 1);
        $referente = $this->userWithRole('referente', 1);

        $this->assertTrue($this->policy->editRoles($cg, $referente));
        $this->assertTrue($this->policy->edit($cg, $referente));
    }

    public function test_control_gestion_cannot_edit_referente_other_ss(): void
    {
        $cg = $this->userWithRoleAndPermission('control-gestion', 'edit_users', 1);
        $referente = $this->userWithRole('referente', 2);

        $this->assertFalse($this->policy->edit($cg, $referente));
    }

    public function test_admin_can_edit_any_user_with_permission(): void
    {
        $adminActor = $this->userWithRoleAndPermission('admin', 'edit_users', 1);
        $target = $this->userWithRole('parametrizador', 2);

        $this->assertTrue($this->policy->edit($adminActor, $target));
        $this->assertTrue($this->policy->editRoles($adminActor, $target));
    }

    public function test_user_can_edit_own_profile_but_not_roles(): void
    {
        $user = $this->userWithRole('referente', 1);

        $this->assertTrue($this->policy->edit($user, $user));
        $this->assertFalse($this->policy->editRoles($user, $user));
    }

    public function test_visualizador_can_read_referente_same_ss(): void
    {
        $visualizador = $this->userWithRole('visualizador', 1);
        $referente = $this->userWithRole('referente', 1);

        $this->assertTrue($this->policy->read($visualizador, $referente));
    }

    public function test_visualizador_cannot_read_referente_other_ss(): void
    {
        $visualizador = $this->userWithRole('visualizador', 1);
        $referente = $this->userWithRole('referente', 2);

        $this->assertFalse($this->policy->read($visualizador, $referente));
    }

    public function test_visualizador_cannot_read_control_gestion_profile(): void
    {
        $visualizador = $this->userWithRole('visualizador', 1);
        $controlGestion = $this->userWithRole('control-gestion', 1);

        $this->assertFalse($this->policy->read($visualizador, $controlGestion));
    }

    public function test_referente_with_read_users_cannot_read_other_ss(): void
    {
        $referente = $this->userWithRoleAndPermission('referente', 'read_users', 1);
        $otherSs = $this->userWithRole('referente', 2);

        $this->assertFalse($this->policy->read($referente, $otherSs));
    }

    public function test_referente_with_read_users_can_read_same_ss(): void
    {
        $referente = $this->userWithRoleAndPermission('referente', 'read_users', 1);
        $sameSs = $this->userWithRole('control-gestion', 1);

        $this->assertTrue($this->policy->read($referente, $sameSs));
    }

    public function test_admin_with_read_users_can_read_any_ss(): void
    {
        $admin = $this->userWithRoleAndPermission('admin', 'read_users', 1);
        $otherSs = $this->userWithRole('referente', 2);

        $this->assertTrue($this->policy->read($admin, $otherSs));
    }

    private function userWithRole(string $roleName, int $ssId): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ssId,
        ]);
    }

    private function userWithRoleAndPermission(string $roleName, string $permissionKey, int $ssId): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $permission = Permission::firstOrCreate(
            ['key' => $permissionKey, 'table_name' => 'users'],
            ['key' => $permissionKey, 'table_name' => 'users'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ssId,
        ]);
    }
}
