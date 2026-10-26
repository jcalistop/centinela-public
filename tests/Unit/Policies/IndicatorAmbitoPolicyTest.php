<?php

namespace Tests\Unit\Policies;

use App\Models\IndicatorAmbito;
use App\Models\Role;
use App\Models\User;
use App\Policies\IndicatorAmbitoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class IndicatorAmbitoPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_referente_cannot_manage_ambito_catalog_without_permissions(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $user = User::factory()->create(['role_id' => $referenteRole->id]);
        $user->role()->associate($referenteRole)->save();

        $ambito = IndicatorAmbito::query()->create([
            'nombre' => 'Test',
            'slug' => 'test',
            'orden' => 1,
            'estado' => true,
        ]);

        $policy = new IndicatorAmbitoPolicy;

        $this->assertFalse($policy->browse($user));
        $this->assertFalse($policy->add($user));
        $this->assertFalse($policy->edit($user, $ambito));
    }

    public function test_parametrizador_can_manage_ambito_catalog_with_voyager_permissions(): void
    {
        $role = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->role()->associate($role)->save();

        $this->grantIndicatorAmbitoPermissions($role);

        $ambito = IndicatorAmbito::query()->create([
            'nombre' => 'Test',
            'slug' => 'test',
            'orden' => 1,
            'estado' => true,
        ]);

        $policy = new IndicatorAmbitoPolicy;

        $this->assertTrue($policy->browse($user));
        $this->assertTrue($policy->add($user));
        $this->assertTrue($policy->edit($user, $ambito));
    }

    public function test_custom_role_with_browse_permission_can_access_catalog(): void
    {
        $role = Role::firstOrCreate(['name' => 'gestor-ambitos'], ['display_name' => 'Gestor ámbitos']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->role()->associate($role)->save();

        $browse = Permission::firstOrCreate(
            ['key' => 'browse_indicator_ambitos'],
            ['table_name' => IndicatorAmbitoPolicy::PERMISSION_TABLE]
        );
        $role->permissions()->syncWithoutDetaching([$browse->id]);

        $policy = new IndicatorAmbitoPolicy;

        $this->assertTrue($policy->browse($user));
        $this->assertFalse($policy->add($user));
    }

    private function grantIndicatorAmbitoPermissions(Role $role): void
    {
        $ids = [];
        foreach (['browse', 'read', 'edit', 'add', 'delete'] as $action) {
            $ids[] = Permission::firstOrCreate(
                ['key' => $action.'_'.IndicatorAmbitoPolicy::PERMISSION_TABLE],
                ['table_name' => IndicatorAmbitoPolicy::PERMISSION_TABLE]
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($ids);
    }
}
