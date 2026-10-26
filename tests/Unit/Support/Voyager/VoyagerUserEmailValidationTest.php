<?php

namespace Tests\Unit\Support\Voyager;

use App\Models\Role;
use App\Models\User;
use App\Support\Voyager\VoyagerUserEmailValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoyagerUserEmailValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_duplicate_email(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        User::factory()->create(['email' => 'duplicado@test.cl']);

        $request = Request::create('/admin/users', 'POST', ['email' => 'duplicado@test.cl']);

        $this->expectException(ValidationException::class);
        VoyagerUserEmailValidation::validate($request);
    }

    public function test_update_allows_keeping_own_email(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $user = User::factory()->create(['email' => 'mismo@test.cl']);
        $request = Request::create('/admin/users/'.$user->id, 'PUT', ['email' => 'mismo@test.cl']);

        VoyagerUserEmailValidation::validate($request, $user->id);

        $this->assertSame('mismo@test.cl', $request->input('email'));
    }

    public function test_update_rejects_email_owned_by_other_user(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        User::factory()->create(['email' => 'otro@test.cl']);
        $user = User::factory()->create(['email' => 'origen@test.cl']);

        $request = Request::create('/admin/users/'.$user->id, 'PUT', ['email' => 'otro@test.cl']);

        $this->expectException(ValidationException::class);
        VoyagerUserEmailValidation::validate($request, $user->id);
    }
}
