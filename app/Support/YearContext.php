<?php

namespace App\Support;

use App\Exceptions\IndicatorYearNotSetException;
use App\Models\Year;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class YearContext
{
    public const SESSION_KEY = 'indicator_year';

    public const DEFAULT_CACHE_KEY = 'default_year';

    public const DEFAULT_CACHE_TTL_SECONDS = 3600;

    /** Request attribute: override de año en superficie agente (no toca sesión web). */
    public const REQUEST_OVERRIDE_KEY = 'centinela.year_override';

    public function current(): ?Year
    {
        if (app()->bound('request')) {
            $override = request()->attributes->get(self::REQUEST_OVERRIDE_KEY);
            if ($override instanceof Year) {
                return $override;
            }
        }

        $year = Session::get(self::SESSION_KEY);

        return $year instanceof Year ? $year : null;
    }

    public function requireCurrent(): Year
    {
        $year = $this->current();

        if (! $year instanceof Year) {
            throw new IndicatorYearNotSetException;
        }

        return $year;
    }

    public function setCurrent(Year $year): void
    {
        Session::put(self::SESSION_KEY, $year);
    }

    public function ensureDefault(): Year
    {
        $current = $this->current();
        if ($current instanceof Year) {
            return $current;
        }

        $year = $this->resolveDefaultYear();
        $this->setCurrent($year);

        return $year;
    }

    public function resolveDefaultYear(): Year
    {
        $cached = Cache::remember(
            self::DEFAULT_CACHE_KEY,
            self::DEFAULT_CACHE_TTL_SECONDS,
            fn () => Year::getDefaultYear()
        );

        if (! $cached instanceof Year) {
            return Year::getDefaultYear();
        }

        return $cached;
    }
}
