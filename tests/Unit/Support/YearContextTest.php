<?php

namespace Tests\Unit\Support;

use App\Exceptions\IndicatorYearNotSetException;
use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class YearContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_null_when_session_has_no_year(): void
    {
        $this->assertNull(app(YearContext::class)->current());
    }

    public function test_set_current_and_current_round_trip(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $context = app(YearContext::class);
        $context->setCurrent($year);

        $this->assertTrue($context->current()->is($year));
    }

    public function test_ensure_default_sets_cached_default_year_when_absent(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        Cache::forget(YearContext::DEFAULT_CACHE_KEY);

        $context = app(YearContext::class);
        $resolved = $context->ensureDefault();

        $this->assertTrue($resolved->is($year));
        $this->assertTrue($context->current()->is($year));
    }

    public function test_ensure_default_keeps_existing_session_year(): void
    {
        $default = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $other = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));

        $context = app(YearContext::class);
        $context->setCurrent($other);

        $this->assertTrue($context->ensureDefault()->is($other));
        $this->assertFalse($context->current()->is($default));
    }

    public function test_require_current_throws_when_year_missing(): void
    {
        $this->expectException(IndicatorYearNotSetException::class);

        app(YearContext::class)->requireCurrent();
    }

    public function test_request_attribute_overrides_session_year(): void
    {
        $sessionYear = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $overrideYear = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));

        $context = app(YearContext::class);
        $context->setCurrent($sessionYear);

        request()->attributes->set(YearContext::REQUEST_OVERRIDE_KEY, $overrideYear);

        $this->assertTrue($context->current()->is($overrideYear));
    }
}
