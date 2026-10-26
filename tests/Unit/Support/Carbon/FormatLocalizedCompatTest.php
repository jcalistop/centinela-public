<?php

namespace Tests\Unit\Support\Carbon;

use App\Support\Carbon\StrftimeFormatConverter;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormatLocalizedCompatTest extends TestCase
{
    #[Test]
    public function converts_voyager_strftime_tokens_to_php_date_format(): void
    {
        $this->assertSame(
            'd-m-Y H:i',
            StrftimeFormatConverter::toPhpFormat('%d-%m-%Y %H:%M')
        );
    }

    #[Test]
    public function format_localized_macro_formats_timestamp_like_voyager_bread(): void
    {
        $formatted = Carbon::parse('2026-10-09 14:30:00')
            ->formatLocalized('%d-%m-%Y %H:%M');

        $this->assertSame('09-10-2026 14:30', $formatted);
    }

    #[Test]
    public function preserves_literal_percent_via_double_percent(): void
    {
        $this->assertSame(
            'd%',
            StrftimeFormatConverter::toPhpFormat('%d%%')
        );
    }
}
