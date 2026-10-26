<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AuthTokenThrottleTest extends TestCase
{
    /** @test */
    public function admin_token_endpoint_returns_too_many_requests_after_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/token', [])->assertStatus(422);
        }

        $this->postJson('/api/admin/token', [])->assertStatus(429);
    }
}
