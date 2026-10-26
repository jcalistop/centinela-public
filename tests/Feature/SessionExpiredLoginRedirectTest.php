<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class SessionExpiredLoginRedirectTest extends TestCase
{
    /** @test */
    public function login_route_forwards_session_expired_query_to_voyager_login(): void
    {
        $this->get('/login?session_expired=1')
            ->assertRedirect(route('voyager.login', ['session_expired' => 1]));
    }

    /** @test */
    public function login_route_forwards_arbitrary_query_parameters_to_voyager_login(): void
    {
        $response = $this->get('/login?session_expired=1&foo=bar');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/admin/login', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('1', $query['session_expired'] ?? null);
        $this->assertSame('bar', $query['foo'] ?? null);
    }

    /** @test */
    public function error_419_view_shows_friendly_copy_and_hides_technical_message_when_debug_disabled(): void
    {
        config(['app.debug' => false]);

        $html = view('errors.419', [
            'exception' => new TokenMismatchException('CSRF token mismatch.'),
        ])->render();

        $this->assertStringContainsString(__('auth.page_title_session_expired'), $html);
        $this->assertStringContainsString(__('http-statuses.419'), $html);
        $this->assertStringNotContainsString('CSRF token mismatch', $html);
        $this->assertStringNotContainsString('<code>', $html);
    }

    /** @test */
    public function error_419_view_shows_exception_message_when_debug_enabled(): void
    {
        config(['app.debug' => true]);

        $html = view('errors.419', [
            'exception' => new TokenMismatchException('CSRF token mismatch.'),
        ])->render();

        $this->assertStringContainsString('CSRF token mismatch', $html);
        $this->assertStringContainsString('<code>', $html);
    }
}
