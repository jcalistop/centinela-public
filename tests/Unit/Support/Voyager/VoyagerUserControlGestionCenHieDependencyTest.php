<?php

namespace Tests\Unit\Support\Voyager;

use App\Models\Role;
use App\Models\User;
use App\Support\Voyager\VoyagerUserControlGestionCenHieDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class VoyagerUserControlGestionCenHieDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_auth_servicio_salud_to_new_user_when_control_gestion(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $actor = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        Auth::login($actor);

        $user = new User;
        $request = Request::create('/admin/users', 'POST', [
            'name' => 'Nuevo',
            'email' => 'nuevo@test.cl',
            'password' => 'claveSegura1',
        ]);

        VoyagerUserControlGestionCenHieDependency::applyToRequestAndModel($user, $request);

        $this->assertSame(1, $user->cen_hie_dependency_id);
        $this->assertSame('1', $request->input('cen_hie_dependency_id'));
    }

    public function test_always_forces_auth_servicio_salud_for_control_gestion(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $actor = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        Auth::login($actor);

        $user = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 2,
        ]);

        $request = Request::create('/admin/users/1', 'PUT', [
            'name' => $user->name,
            'email' => $user->email,
            'cen_hie_dependency_id' => '2',
        ]);

        VoyagerUserControlGestionCenHieDependency::applyToRequestAndModel($user, $request);

        $this->assertSame(1, $user->cen_hie_dependency_id);
        $this->assertSame('1', $request->input('cen_hie_dependency_id'));
    }
}
