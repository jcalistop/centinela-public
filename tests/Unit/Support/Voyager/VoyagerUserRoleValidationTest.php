<?php

namespace Tests\Unit\Support\Voyager;

use App\Models\Role;
use App\Support\Voyager\VoyagerUserRoleValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoyagerUserRoleValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_empty_role_id(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $request = Request::create('/admin/users', 'POST', [
            'email' => 'nuevo@test.cl',
            'role_id' => '',
        ]);

        $this->expectException(ValidationException::class);
        VoyagerUserRoleValidation::validate($request);
    }

    public function test_accepts_valid_role_id(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $request = Request::create('/admin/users', 'POST', [
            'email' => 'nuevo@test.cl',
            'role_id' => (string) $role->id,
        ]);

        VoyagerUserRoleValidation::validate($request);

        $this->assertSame((string) $role->id, $request->input('role_id'));
    }

    public function test_maps_relationship_field_to_role_id_before_validate(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $request = Request::create('/admin/users', 'POST', [
            'email' => 'nuevo@test.cl',
            'user_belongsto_role_relationship' => (string) $role->id,
        ]);

        VoyagerUserRoleValidation::validate($request);

        $this->assertSame((string) $role->id, $request->input('role_id'));
    }
}
