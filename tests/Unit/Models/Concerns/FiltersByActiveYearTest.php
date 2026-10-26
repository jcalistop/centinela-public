<?php

namespace Tests\Unit\Models\Concerns;

use App\Models\ComCommitment;
use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiltersByActiveYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_year_scope_filters_by_session_year(): void
    {
        $active = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $other = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));
        $context = app(YearContext::class);

        $context->setCurrent($active);
        ComCommitment::unguarded(fn () => ComCommitment::query()->create([
            'clave' => 'A',
            'nombre' => 'Activo',
            'year_id' => $active->id,
            'indicator_type_id' => 1,
        ]));

        $context->setCurrent($other);
        ComCommitment::unguarded(fn () => ComCommitment::query()->create([
            'clave' => 'B',
            'nombre' => 'Otro',
            'year_id' => $other->id,
            'indicator_type_id' => 1,
        ]));

        $context->setCurrent($active);

        $rows = ComCommitment::query()->currentYear()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('A', $rows->first()->clave);
    }

    public function test_current_year_scope_returns_empty_without_session_year(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $context = app(YearContext::class);
        $context->setCurrent($year);

        ComCommitment::unguarded(fn () => ComCommitment::query()->create([
            'clave' => 'A',
            'nombre' => 'Activo',
            'year_id' => $year->id,
            'indicator_type_id' => 1,
        ]));

        session()->forget(YearContext::SESSION_KEY);

        $this->assertCount(0, ComCommitment::query()->currentYear()->get());
    }
}
