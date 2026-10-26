<?php

namespace App\Livewire\Adp\Traits;

use App\Models\IndicatorType;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Facades\Auth;

trait ResolvesAdpInstrument
{
    public ?int $indicatorTypeId = null;

    protected int $yearId;

    protected function resolveIndicatorTypeId(?int $passed = null): int
    {
        if ($passed !== null) {
            return $passed;
        }

        $routeType = request()->route('indicatorType');
        if ($routeType instanceof IndicatorType) {
            return $routeType->id;
        }

        return IndicatorType::query()->where('route', 'adp')->firstOrFail()->id;
    }

    protected function authUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function indicatorYear(): Year
    {
        $year = session('indicator_year');
        abort_unless($year instanceof Year, 403);

        return $year;
    }
}
