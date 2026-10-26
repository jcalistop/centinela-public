<?php

namespace App\Services\Users;

use App\Helpers\Helper;
use App\Models\ComIndicator;
use App\Models\MetIndicatorReferente;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Collection;

final class UserReferentIndicatorsService
{
    /**
     * Indicadores COMGES y Metas en los que el usuario es referente (año y SS del perfil).
     *
     * @return Collection<int, array{
     *     module: string,
     *     instrument: string,
     *     clave: string,
     *     nombre: string,
     *     year: int,
     *     url: string|null
     * }>
     */
    public function forUser(User $user, ?Year $year = null): Collection
    {
        $year = $year ?? Helper::showCurrentYear();
        if (! $year instanceof Year) {
            return collect();
        }

        $dependencyId = $user->cen_hie_dependency_id;
        if ($dependencyId === null) {
            return collect();
        }

        return $this->metasRows($user, (int) $dependencyId, $year)
            ->concat($this->comgesRows($user, (int) $dependencyId, $year))
            ->sortBy([
                ['module', 'asc'],
                ['instrument', 'asc'],
                ['clave', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{module: string, instrument: string, clave: string, nombre: string, year: int, url: string|null}>
     */
    private function metasRows(User $user, int $dependencyId, Year $year): Collection
    {
        return MetIndicatorReferente::query()
            ->where('user_id', $user->getKey())
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->with([
                'metIndicator.indicatorType:id,route,nombre_indicador',
                'metIndicator:id,indicator_type_id,clave,nombre,year_id',
            ])
            ->get()
            ->map(function (MetIndicatorReferente $assignment): ?array {
                $indicator = $assignment->metIndicator;
                if ($indicator === null) {
                    return null;
                }

                $indicatorType = $indicator->indicatorType;
                $url = null;
                if ($indicatorType?->route !== null && $indicatorType->route !== '') {
                    $url = route('metas.instrument.indicators.show', [$indicatorType, $indicator]);
                }

                return [
                    'module' => 'Metas',
                    'instrument' => $indicatorType?->nombre_indicador ?? 'Metas',
                    'clave' => $indicator->clave,
                    'nombre' => $indicator->nombre,
                    'year' => (int) $indicator->year_id,
                    'url' => $url,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{module: string, instrument: string, clave: string, nombre: string, year: int, url: string|null}>
     */
    private function comgesRows(User $user, int $dependencyId, Year $year): Collection
    {
        return ComIndicator::query()
            ->whereHas('comCommitment', fn ($query) => $query->where('year_id', $year->id))
            ->whereHas('comIndicatorSettings', function ($query) use ($dependencyId, $user): void {
                $query->where('cen_hie_dependency_id', $dependencyId)
                    ->whereHas('users', fn ($users) => $users->whereKey($user->getKey()));
            })
            ->with(['comCommitment:id,nombre,year_id'])
            ->orderBy('orden')
            ->orderBy('clave')
            ->get()
            ->map(function (ComIndicator $indicator): array {
                return [
                    'module' => 'COMGES',
                    'instrument' => $indicator->comCommitment?->nombre ?? 'Compromiso de Gestión',
                    'clave' => $indicator->clave,
                    'nombre' => $indicator->nombre,
                    'year' => (int) ($indicator->comCommitment?->year_id ?? 0),
                    'url' => route('voyager.indicadores-comges.show', $indicator->getKey()),
                ];
            });
    }
}
