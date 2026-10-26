<?php

namespace App\Services\Cen;

use App\Models\CenAddress;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

final class CenCenterBrowseService
{
    public const SESSION_KEY = 'cen_centers_browse_ss_id';

    private const CHILE_LAT_MIN = -56.0;

    private const CHILE_LAT_MAX = -17.0;

    private const CHILE_LNG_MIN = -76.0;

    private const CHILE_LNG_MAX = -66.0;

    private const LIST_PER_PAGE = 15;

    /** @var list<array{key: string, label: string}> */
    private const LIST_COLUMNS = [
        ['key' => 'establecimiento', 'label' => 'Establecimiento'],
        ['key' => 'codigo', 'label' => 'Código'],
        ['key' => 'tipo', 'label' => 'Tipo de establecimiento'],
        ['key' => 'admDependency', 'label' => 'Dependencia administrativa'],
        ['key' => 'region', 'label' => 'Región'],
        ['key' => 'commune', 'label' => 'Comuna'],
        ['key' => 'geo', 'label' => 'Geo'],
    ];

    /**
     * @return array{
     *     ssNombre: string|null,
     *     ssCodigo: string|null,
     *     effectiveSsId: int|null,
     *     exploringForeignSs: bool,
     *     ssOptions: list<array{id: int, nombre: string, codigo: string|null}>,
     *     markers: list<array<string, mixed>>,
     *     listItems: list<array<string, mixed>>,
     *     listColumns: list<array{key: string, label: string}>,
     *     filterOptions: array<string, mixed>,
     *     listPerPage: int,
     *     georeferencedCount: int,
     *     withoutCoordinatesCount: int,
     *     totalCount: int,
     *     canManageBitacora: bool
     * }
     */
    public function buildForUser(User $user, ?string $dataTypeScope = null): array
    {
        $canManageBitacora = $user->hasRole('admin');
        $effectiveSsId = $this->effectiveBrowseSsId($user);
        $baseQuery = $this->scopedCentersQueryForBrowse($user);

        $totalCount = (clone $baseQuery)->count();

        $centers = (clone $baseQuery)
            ->with(['cenAddress.cenCommune.cenProvince.cenRegion', 'cenHieDependency'])
            ->orderBy('nombre_corto')
            ->get();

        $markers = [];
        $listItems = [];
        $georeferencedCount = 0;
        /** @var Collection<string, CenCenter> $centersByCodigo */
        $centersByCodigo = $centers->keyBy('codigo');

        foreach ($centers as $center) {
            $address = $center->cenAddress;
            $payload = $this->filterPayload($center, $address, $canManageBitacora);
            $hasCoordinates = $this->hasValidCoordinates($address);

            if ($hasCoordinates) {
                $georeferencedCount++;
                $markers[] = array_merge($payload, [
                    'lat' => (float) $address->latitud,
                    'lng' => (float) $address->longitud,
                ]);
            }

            $listItems[] = array_merge($payload, [
                'id' => $center->getKey(),
                'hasCoordinates' => $hasCoordinates,
                'madreDescripcion' => $this->resolveMadreDescripcion($center, $centersByCodigo),
                'regionNombre' => $address?->cenCommune?->cenProvince?->cenRegion?->nombre,
            ]);
        }

        $withoutCoordinatesCount = max(0, $totalCount - $georeferencedCount);

        $dependency = $effectiveSsId !== null
            ? CenHieDependency::query()->find($effectiveSsId)
            : null;

        return [
            'ssNombre' => $dependency?->nombre,
            'ssCodigo' => $dependency?->codigo,
            'effectiveSsId' => $effectiveSsId,
            'exploringForeignSs' => $this->isExploringForeignSs($user),
            'ssOptions' => $this->servicioOptions(),
            'markers' => $markers,
            'listItems' => $listItems,
            'listColumns' => self::LIST_COLUMNS,
            'filterOptions' => $this->buildFilterOptions($listItems),
            'listPerPage' => self::LIST_PER_PAGE,
            'georeferencedCount' => $georeferencedCount,
            'withoutCoordinatesCount' => $withoutCoordinatesCount,
            'totalCount' => $totalCount,
            'canManageBitacora' => $canManageBitacora,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterPayload(CenCenter $center, ?CenAddress $address, bool $canManageBitacora): array
    {
        $commune = $address?->cenCommune;
        $province = $commune?->cenProvince;

        return [
            'codigo' => $center->codigo,
            'nombre' => $center->nombre_corto ?? $center->nombre_oficial,
            'tipo' => $center->cen_type?->value,
            'admDependency' => $center->cen_adm_dependency?->value,
            'nivelAtencion' => $center->nivel_atencion?->value,
            'tieneUrgencia' => $center->tiene_urgencia?->value,
            'provinceId' => $province?->id,
            'provinceNombre' => $province?->nombre,
            'communeId' => $commune?->id,
            'communeNombre' => $commune?->nombre,
            'pendiente' => $canManageBitacora && (bool) $center->tarea_pendiente,
            'showUrl' => $this->showUrl($center->getKey()),
        ];
    }

    private function hasValidCoordinates(?CenAddress $address): bool
    {
        if ($address === null || $address->latitud === null || $address->longitud === null) {
            return false;
        }

        $lat = (float) $address->latitud;
        $lng = (float) $address->longitud;

        return $lat >= self::CHILE_LAT_MIN
            && $lat <= self::CHILE_LAT_MAX
            && $lng >= self::CHILE_LNG_MIN
            && $lng <= self::CHILE_LNG_MAX;
    }

    /**
     * @param  Collection<string, CenCenter>  $centersByCodigo
     */
    private function resolveMadreDescripcion(CenCenter $center, Collection $centersByCodigo): ?string
    {
        $codigoMadre = trim((string) ($center->codigo_madre ?? ''));

        if ($codigoMadre === '') {
            return null;
        }

        $madre = $centersByCodigo->get($codigoMadre);

        if (! $madre instanceof CenCenter) {
            return sprintf('Establecimiento madre: %s', $codigoMadre);
        }

        $nombreMadre = trim((string) ($madre->nombre_corto ?? $madre->nombre_oficial ?? ''));

        if ($nombreMadre === '') {
            return sprintf('Establecimiento madre: %s', $codigoMadre);
        }

        if ($nombreMadre === $codigoMadre) {
            return sprintf('Establecimiento madre: %s', $nombreMadre);
        }

        return sprintf('Establecimiento madre: %s (%s)', $nombreMadre, $codigoMadre);
    }

    /**
     * @param  list<array<string, mixed>>  $listItems
     * @return array<string, mixed>
     */
    private function buildFilterOptions(array $listItems): array
    {
        return [
            'establishments' => $this->distinctEstablishments($listItems),
            'tipo' => $this->distinctStringOptions($listItems, 'tipo'),
            'admDependency' => $this->distinctStringOptions($listItems, 'admDependency'),
            'nivelAtencion' => $this->distinctStringOptions($listItems, 'nivelAtencion'),
            'tieneUrgencia' => $this->distinctStringOptions($listItems, 'tieneUrgencia'),
            'provinces' => $this->distinctProvinces($listItems),
            'communes' => $this->distinctCommunes($listItems),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{codigo: string, label: string}>
     */
    private function distinctEstablishments(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => isset($item['codigo']) && is_string($item['codigo']) && $item['codigo'] !== '')
            ->unique('codigo')
            ->sortBy(fn (array $item) => mb_strtolower((string) ($item['nombre'] ?? $item['codigo'])))
            ->map(fn (array $item) => [
                'codigo' => $item['codigo'],
                'label' => sprintf('%s (%s)', $item['nombre'] ?? $item['codigo'], $item['codigo']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{value: string, label: string}>
     */
    private function distinctStringOptions(array $items, string $key): array
    {
        $values = collect($items)
            ->pluck($key)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->sort()
            ->values();

        return $values
            ->map(fn (string $value) => ['value' => $value, 'label' => $value])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{id: int, nombre: string}>
     */
    private function distinctProvinces(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => isset($item['provinceId']) && is_int($item['provinceId']))
            ->unique('provinceId')
            ->sortBy('provinceNombre')
            ->map(fn (array $item) => [
                'id' => $item['provinceId'],
                'nombre' => (string) ($item['provinceNombre'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{id: int, nombre: string, provinceId: int}>
     */
    private function distinctCommunes(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => isset($item['communeId'], $item['provinceId'])
                && is_int($item['communeId'])
                && is_int($item['provinceId']))
            ->unique('communeId')
            ->sortBy('communeNombre')
            ->map(fn (array $item) => [
                'id' => $item['communeId'],
                'nombre' => (string) ($item['communeNombre'] ?? ''),
                'provinceId' => $item['provinceId'],
            ])
            ->values()
            ->all();
    }

    public function applyRequestedSs(User $user, mixed $requested): ?int
    {
        if ($requested !== null && $requested !== '') {
            $id = filter_var($requested, FILTER_VALIDATE_INT);

            if (is_int($id) && $id > 0 && $this->validServicioSsId($id) !== null) {
                Session::put(self::SESSION_KEY, $id);
            } else {
                Session::forget(self::SESSION_KEY);
            }
        }

        return $this->effectiveBrowseSsId($user);
    }

    public function effectiveBrowseSsId(User $user): ?int
    {
        $sessionId = Session::get(self::SESSION_KEY);
        $fromSession = is_numeric($sessionId) ? $this->validServicioSsId((int) $sessionId) : null;

        if ($fromSession !== null) {
            return $fromSession;
        }

        $own = $user->cen_hie_dependency_id;

        if (is_numeric($own) && (int) $own > 0) {
            return (int) $own;
        }

        return null;
    }

    public function isExploringForeignSs(User $user): bool
    {
        $own = $user->cen_hie_dependency_id;

        if ($own === null) {
            return false;
        }

        $effective = $this->effectiveBrowseSsId($user);

        return $effective !== null && $effective !== (int) $own;
    }

    /**
     * @return list<array{id: int, nombre: string, codigo: string|null}>
     */
    public function servicioOptions(): array
    {
        return CenHieDependency::query()
            ->where('tipo_dependencia', 'SERVICIO')
            ->orderBy('codigo')
            ->get(['id', 'nombre', 'codigo'])
            ->map(fn (CenHieDependency $dependency): array => [
                'id' => (int) $dependency->id,
                'nombre' => (string) $dependency->nombre,
                'codigo' => is_string($dependency->codigo) ? $dependency->codigo : null,
            ])
            ->values()
            ->all();
    }

    public function scopedCentersQueryForBrowse(User $user): Builder
    {
        $query = CenCenter::query();
        $ssId = $this->effectiveBrowseSsId($user);

        if ($ssId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('cen_hie_dependency_id', $ssId);
    }

    public function scopedCentersQueryForUser(User $user, ?string $dataTypeScope): Builder
    {
        $query = CenCenter::query();

        if ($dataTypeScope !== null && $dataTypeScope !== '' && method_exists(CenCenter::class, 'scope'.ucfirst($dataTypeScope))) {
            $query->{$dataTypeScope}();
        }

        if ($user->cen_hie_dependency_id !== null) {
            $query->where('cen_hie_dependency_id', $user->cen_hie_dependency_id);
        }

        return $query;
    }

    private function validServicioSsId(int $id): ?int
    {
        $exists = CenHieDependency::query()
            ->whereKey($id)
            ->where('tipo_dependencia', 'SERVICIO')
            ->exists();

        return $exists ? $id : null;
    }

    private function showUrl(int|string $centerId): string
    {
        if (Route::has('voyager.establecimientos.show')) {
            return route('voyager.establecimientos.show', $centerId);
        }

        return url('/admin/establecimientos/'.$centerId);
    }
}
