<?php

declare(strict_types=1);

namespace App\Support\Voyager;

use App\Models\Year;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo de años para el dropdown de la navbar Voyager (SDD-174).
 */
final class YearDropdownCatalog
{
    public const CACHE_KEY = 'years_dropdown_catalog';

    /**
     * @return Collection<int, Year>
     */
    public static function all(): Collection
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): Collection => Year::query()->orderBy('ano')->get()
        );
    }

    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
