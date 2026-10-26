<?php

namespace Tests\Unit\Support\Voyager;

use App\Support\Voyager\VoyagerUserPasswordValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoyagerUserPasswordValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_password_with_min_length(): void
    {
        $request = Request::create('/admin/users', 'POST', [
            'name' => 'Test',
            'email' => 't@test.cl',
            'password' => '',
        ]);

        $this->expectException(ValidationException::class);
        VoyagerUserPasswordValidation::validateForStore($request);
    }

    public function test_store_accepts_valid_password(): void
    {
        $request = Request::create('/admin/users', 'POST', [
            'name' => 'Test',
            'email' => 't@test.cl',
            'password' => 'secreto88',
        ]);

        VoyagerUserPasswordValidation::validateForStore($request);

        $this->assertSame('secreto88', $request->input('password'));
    }

    public function test_normalize_update_strips_empty_password(): void
    {
        $request = Request::create('/admin/users/1', 'PUT', [
            'password' => '',
            'name' => 'X',
        ]);

        VoyagerUserPasswordValidation::normalizeRequestForUpdate($request);

        $this->assertFalse($request->has('password'));
    }

    public function test_optional_update_validates_when_password_sent(): void
    {
        $request = Request::create('/admin/users/1', 'PUT', [
            'password' => 'abc',
        ]);

        $this->expectException(ValidationException::class);
        VoyagerUserPasswordValidation::validateForOptionalPasswordOnUpdate($request);
    }

    public function test_sending_credentials_requires_password(): void
    {
        $request = Request::create('/admin/users/1', 'PUT', [
            'send_credentials_email' => '1',
            'password' => '',
        ]);

        $this->expectException(ValidationException::class);
        VoyagerUserPasswordValidation::validatePasswordRequiredWhenSendingCredentials($request);
    }
}
