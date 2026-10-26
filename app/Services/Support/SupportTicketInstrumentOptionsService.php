<?php

namespace App\Services\Support;

use App\Helpers\ModuleAccess;
use App\Models\IndicatorType;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SupportTicketInstrumentOptionsService
{
    /**
     * Instrumentos habilitados para el SS según la misma jerarquía que el menú lateral.
     *
     * @return Collection<int, IndicatorType>
     */
    public function forServicioSalud(int $ssId, ?User $user = null, ?Year $year = null): Collection
    {
        if ($ssId <= 0) {
            return collect();
        }

        $year = $year ?? session('indicator_year');
        if (! $year instanceof Year) {
            return collect();
        }

        $user = $user ?? Auth::user();

        return IndicatorType::query()
            ->orderBy('nombre_indicador')
            ->get(['id', 'nombre_indicador', 'route', 'estado'])
            ->filter(fn (IndicatorType $type): bool => ModuleAccess::isInstrumentAvailableForServicioSalud($type, $ssId, $user))
            ->values();
    }

    public function isIndicatorAllowedForServicioSalud(
        int $indicatorTypeId,
        int $ssId,
        ?User $user = null,
        ?Year $year = null,
    ): bool {
        if ($indicatorTypeId <= 0 || $ssId <= 0) {
            return false;
        }

        $type = IndicatorType::query()->find($indicatorTypeId);
        if ($type === null) {
            return false;
        }

        $year = $year ?? session('indicator_year');
        if (! $year instanceof Year) {
            return false;
        }

        $user = $user ?? Auth::user();

        return ModuleAccess::isInstrumentAvailableForServicioSalud($type, $ssId, $user);
    }
}
