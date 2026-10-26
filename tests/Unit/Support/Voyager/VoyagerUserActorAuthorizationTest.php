<?php

namespace Tests\Unit\Support\Voyager;

use App\Models\Role;
use App\Models\User;
use App\Support\Voyager\VoyagerUserActorAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoyagerUserActorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_protected_role_for_admin_and_parametrizador(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $param = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $referente = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->assertTrue(VoyagerUserActorAuthorization::isProtectedRole($admin));
        $this->assertTrue(VoyagerUserActorAuthorization::isProtectedRole($param));
        $this->assertFalse(VoyagerUserActorAuthorization::isProtectedRole($referente));
    }

    public function test_control_gestion_cannot_manage_admin_same_ss(): void
    {
        $actor = $this->userWithRole('control-gestion', 1);
        $target = $this->userWithRole('admin', 1);

        $this->assertFalse(VoyagerUserActorAuthorization::canManageUser($actor, $target));
    }

    public function test_control_gestion_can_manage_referente_same_ss(): void
    {
        $actor = $this->userWithRole('control-gestion', 1);
        $target = $this->userWithRole('referente', 1);

        $this->assertTrue(VoyagerUserActorAuthorization::canManageUser($actor, $target));
    }

    public function test_control_gestion_cannot_manage_referente_other_ss(): void
    {
        $actor = $this->userWithRole('control-gestion', 1);
        $target = $this->userWithRole('referente', 2);

        $this->assertFalse(VoyagerUserActorAuthorization::canManageUser($actor, $target));
    }

    public function test_control_gestion_can_manage_new_user_for_create_when_ss_matches_actor(): void
    {
        $actor = $this->userWithRole('control-gestion', 1);
        $target = new User;
        $target->cen_hie_dependency_id = 1;

        $this->assertTrue(VoyagerUserActorAuthorization::canManageUser($actor, $target));
    }

    public function test_control_gestion_infers_actor_ss_for_unsaved_user_on_create(): void
    {
        $actor = $this->userWithRole('control-gestion', 1);
        $this->actingAs($actor);

        $this->assertTrue(VoyagerUserActorAuthorization::canManageUser($actor, new User));
    }

    public function test_control_gestion_cannot_manage_new_user_for_other_ss(): void
    {
        $actor = $this->userWithRole('control-gestion', 1);
        $target = new User;
        $target->cen_hie_dependency_id = 2;

        $this->assertFalse(VoyagerUserActorAuthorization::canManageUser($actor, $target));
    }

    public function test_control_gestion_cannot_assign_admin_role(): void
    {
        $actor = $this->userWithRole('control-gestion', 1);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $this->assertFalse(VoyagerUserActorAuthorization::canAssignRole($actor, $adminRole));
    }

    public function test_assert_can_manage_user_throws_for_protected_target(): void
    {
        $actor = $this->userWithRole('control-gestion', 1);
        $target = $this->userWithRole('admin', 1);

        $this->expectException(AuthorizationException::class);
        VoyagerUserActorAuthorization::assertCanManageUser($actor, $target);
    }

    private function userWithRole(string $roleName, int $ssId): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ssId,
        ]);
    }
}
