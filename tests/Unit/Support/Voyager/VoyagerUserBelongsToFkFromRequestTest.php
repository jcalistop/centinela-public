<?php

namespace Tests\Unit\Support\Voyager;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Support\Voyager\VoyagerUserBelongsToFkFromRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class VoyagerUserBelongsToFkFromRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_role_id_from_relationship_post_key(): void
    {
        $roleA = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $roleB = Role::query()->firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);

        $user = User::factory()->create([
            'role_id' => $roleA->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $request = Request::create('/admin/users/'.$user->id, 'PUT', [
            'user_belongsto_role_relationship' => (string) $roleB->id,
        ]);

        VoyagerUserBelongsToFkFromRequest::sync($user, $request);

        $this->assertSame($roleB->id, $user->fresh()->role_id);
    }

    public function test_syncs_role_id_from_role_id_column_post_key(): void
    {
        $roleA = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $roleB = Role::query()->firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);

        $user = User::factory()->create([
            'role_id' => $roleA->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $request = Request::create('/admin/users/'.$user->id, 'PUT', [
            'role_id' => (string) $roleB->id,
        ]);

        VoyagerUserBelongsToFkFromRequest::sync($user, $request);

        $this->assertSame($roleB->id, $user->fresh()->role_id);
    }

    public function test_self_edit_preserves_own_role_without_authorization_guards(): void
    {
        $referenteRole = Role::query()->firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $user = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $request = Request::create('/admin/users/'.$user->id, 'PUT', [
            'role_id' => (string) $referenteRole->id,
        ]);

        $this->actingAs($user);

        VoyagerUserBelongsToFkFromRequest::sync($user, $request);

        $this->assertSame($referenteRole->id, $user->fresh()->role_id);
    }

    public function test_syncs_cen_hie_dependency_id_from_relationship_post_key(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $now = now();
        CenHieDependency::unguarded(fn () => CenHieDependency::query()->insert([
            ['id' => 1, 'codigo' => 'SS01', 'nombre' => 'SS Uno', 'tipo_dependencia' => 'SERVICIO', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'codigo' => 'SS02', 'nombre' => 'SS Dos', 'tipo_dependencia' => 'SERVICIO', 'created_at' => $now, 'updated_at' => $now],
        ]));

        $user = User::factory()->create(['cen_hie_dependency_id' => 1]);

        $request = Request::create('/admin/users/'.$user->id, 'PUT', [
            'user_belongsto_cen_hie_dependency_relationship' => '2',
        ]);

        VoyagerUserBelongsToFkFromRequest::sync($user, $request);

        $this->assertSame(2, $user->fresh()->cen_hie_dependency_id);
    }

    public function test_syncs_cen_hie_dependency_id_from_column_post_key(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $now = now();
        CenHieDependency::unguarded(fn () => CenHieDependency::query()->insert([
            ['id' => 1, 'codigo' => 'SS01', 'nombre' => 'SS Uno', 'tipo_dependencia' => 'SERVICIO', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'codigo' => 'SS02', 'nombre' => 'SS Dos', 'tipo_dependencia' => 'SERVICIO', 'created_at' => $now, 'updated_at' => $now],
        ]));

        $user = User::factory()->create(['cen_hie_dependency_id' => 1]);

        $request = Request::create('/admin/users/'.$user->id, 'PUT', [
            'cen_hie_dependency_id' => '2',
        ]);

        VoyagerUserBelongsToFkFromRequest::sync($user, $request);

        $this->assertSame(2, $user->fresh()->cen_hie_dependency_id);
    }
}
