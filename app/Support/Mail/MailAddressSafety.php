<?php

namespace App\Support\Mail;

use InvalidArgumentException;

final class MailAddressSafety
{
    public static function rejectIfContainsCrlf(string $value): void
    {
        if (preg_match('/[\r\n]/', $value) > 0) {
            throw new InvalidArgumentException('Mail address contains invalid CRLF characters.');
        }
    }
}
