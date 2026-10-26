<?php

namespace Tests\Unit\Support\Mail;

use App\Support\Mail\MailAddressSafety;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MailAddressSafetyTest extends TestCase
{
    #[Test]
    public function test_allows_safe_strings(): void
    {
        MailAddressSafety::rejectIfContainsCrlf('usuario@example.test');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function test_rejects_crlf_in_address(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MailAddressSafety::rejectIfContainsCrlf("\"foo\r\nBcc: victim@example.test\"@example.test");
    }

    #[Test]
    public function test_rejects_crlf_in_display_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MailAddressSafety::rejectIfContainsCrlf("Nombre\r\nEvil");
    }
}
