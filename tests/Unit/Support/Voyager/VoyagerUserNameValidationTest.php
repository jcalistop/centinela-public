<?php

namespace Tests\Unit\Support\Voyager;

use App\Support\Voyager\VoyagerUserNameValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoyagerUserNameValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_missing_name(): void
    {
        $request = Request::create('/admin/users', 'POST', ['email' => 'x@test.cl']);

        $this->expectException(ValidationException::class);
        VoyagerUserNameValidation::validate($request);
    }

    public function test_rejects_blank_name(): void
    {
        $request = Request::create('/admin/users', 'POST', [
            'name' => '   ',
            'email' => 'x@test.cl',
        ]);

        $this->expectException(ValidationException::class);
        VoyagerUserNameValidation::validate($request);
    }

    public function test_trims_and_merges_valid_name(): void
    {
        $request = Request::create('/admin/users', 'POST', [
            'name' => '  Juan Pérez  ',
            'email' => 'x@test.cl',
        ]);

        VoyagerUserNameValidation::validate($request);

        $this->assertSame('Juan Pérez', $request->input('name'));
    }
}
