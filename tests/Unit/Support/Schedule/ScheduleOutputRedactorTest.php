<?php

namespace Tests\Unit\Support\Schedule;

use App\Support\Schedule\ScheduleOutputRedactor;
use Tests\TestCase;

class ScheduleOutputRedactorTest extends TestCase
{
    public function test_redacts_email_and_secrets_and_truncates(): void
    {
        $input = "ok user@example.com Bearer abc.def.ghi password=supersecret\n".str_repeat('x', 3000);

        $out = ScheduleOutputRedactor::redact($input, 100);

        $this->assertStringNotContainsString('user@example.com', $out);
        $this->assertStringContainsString('[email-redacted]', $out);
        $this->assertStringContainsString('Bearer [redacted]', $out);
        $this->assertStringContainsString('password=[redacted]', $out);
        $this->assertStringContainsString('[truncated]', $out);
        $this->assertLessThanOrEqual(120, mb_strlen($out));
    }
}
