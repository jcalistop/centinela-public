<?php

namespace App\Helpers;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Support\Configuration\ConfigurationService;
use Illuminate\Support\Facades\Auth;

/**
 * Helper para verificar el acceso a modulos de la plataforma.
 *
 * Jerarquia de control (instrumentos Metas/COMGES/ADP) — SDD-263:
 * 1. Capa 1 — indicator_types.estado: si Off, denegar a todos (incluido admin/parametrizador).
 * 2. Capa 2 — toggle SS / habilitado anual / SS habilitado: si Off, denegar a todos
 *    excepto admin y parametrizador (bypass de capa 2). control-gestion no bypasea capa 2.
 *
 * Modulos de soporte/chat/manual: settings globales + SS + toggle (sin capa 1 de catálogo).
 * Admin/parametrizador bypasean esos modulos de soporte (sin IndicatorType).
 *
 * Memo request-scoped (SDD-174): evita N+1 en el menú lateral al filtrar varios instrumentos.
 */
class ModuleAccess
{
    /**
     * Modulos cuya habilitacion global proviene de indicator_types.estado (route => moduleKey).
     *
     * @var array<string, string>
     */
    private const INDICATOR_TYPE_MODULE_ROUTES = [
        'comges' => 'voyager.compromisos.index',
        'metas-18834' => 'metas-18834',
        'metas-19664' => 'metas-19664',
        'metas-20707' => 'metas-20707',
        'adp' => 'adp',
    ];

    /** @var array{ss: array<int, CenHieDependency|false>, indicators: array<string, IndicatorType|false>, habilitado: array<string, array<int, true>>} */
    private static array $memo = [
        'ss' => [],
        'indicators' => [],
        'habilitado' => [],
    ];

    /**
     * Limpia la memoización request-scoped (tests / jobs largos).
     */
    public static function flushRequestMemo(): void
    {
        self::$memo = [
            'ss' => [],
            'indicators' => [],
            'habilitado' => [],
        ];
        ServiceModuleToggle::flushRequestMemo();
    }

    /**
     * Mapeo inverso: route del IndicatorType => moduleKey del toggle.
     *
     * @return array<string, string>
     */
    private static function indicatorRouteToModuleKey(): array
    {
        return array_flip(self::INDICATOR_TYPE_MODULE_ROUTES);
    }

    public static function toggleModuleKeyForRoute(string $route): string
    {
        return self::indicatorRouteToModuleKey()[$route] ?? $route;
    }

    /**
     * Route de IndicatorType asociada a un module_key de toggle, o null si no es instrumento de catálogo.
     */
    public static function indicatorRouteForModuleKey(string $moduleKey): ?string
    {
        return self::INDICATOR_TYPE_MODULE_ROUTES[$moduleKey] ?? null;
    }

    /**
     * Capa 1: el instrumento de catálogo está activo (estado=true).
     * Retorna null si el module_key no mapea a un IndicatorType conocido.
     */
    public static function isCatalogInstrumentActive(string $moduleKey): ?bool
    {
        $route = self::indicatorRouteForModuleKey($moduleKey);
        if ($route === null) {
            return null;
        }

        $indicatorType = self::indicatorTypeByRoute($route);

        if ($indicatorType === null) {
            return false;
        }

        return (bool) $indicatorType->estado;
    }

    /**
     * Instrumento disponible para un SS en contexto de tickets (sin bypass admin de canAccess).
     */
    public static function isInstrumentAvailableForServicioSalud(
        IndicatorType $indicatorType,
        int $ssId,
        ?User $user = null,
    ): bool {
        $user = $user ?? Auth::user();

        $route = (string) $indicatorType->route;
        if ($route === '') {
            return false;
        }

        if (! $indicatorType->estado) {
            return false;
        }

        $toggleModuleKey = self::toggleModuleKeyForRoute($route);

        $ss = self::resolveServicioSalud($ssId);
        if ($ss === null) {
            return false;
        }

        if (! ServiceModuleToggle::isEnabled($ssId, $toggleModuleKey)) {
            return false;
        }

        if ($user instanceof User && $user->hasRole(['referente', 'visualizador'])) {
            return self::indicatorYearHabilitadoForSs($indicatorType, $ssId);
        }

        if ($user instanceof User && $user->hasRole(['control-gestion', 'admin', 'parametrizador'])) {
            return true;
        }

        return true;
    }

    /**
     * Determina si un modulo esta accesible para el SS del usuario actual.
     *
     * @param  string  $moduleKey  Identificador del modulo (ej. 'metas-18834', 'adp')
     *                             o route del IndicatorType (ej. 'voyager.compromisos.index').
     * @param  int|null  $ssId  ID del Servicio de Salud. Por defecto usa el del usuario autenticado.
     * @param  IndicatorType|null  $knownIndicatorType  Modelo ya cargado (evita re-query por route en el menú).
     */
    public static function canAccess(string $moduleKey, ?int $ssId = null, ?IndicatorType $knownIndicatorType = null): bool
    {
        $user = Auth::user();

        $routeToModule = self::indicatorRouteToModuleKey();
        $toggleModuleKey = $routeToModule[$moduleKey] ?? $moduleKey;
        $indicatorRoute = self::INDICATOR_TYPE_MODULE_ROUTES[$toggleModuleKey] ?? null;

        if ($indicatorRoute !== null) {
            return self::canAccessKnownIndicatorModule(
                $indicatorRoute,
                $toggleModuleKey,
                $ssId,
                $user,
                $knownIndicatorType
            );
        }

        $dynamicIndicator = $knownIndicatorType;
        if ($dynamicIndicator === null || (string) $dynamicIndicator->route !== $moduleKey) {
            $dynamicIndicator = self::indicatorTypeByRoute($moduleKey);
        }

        if ($dynamicIndicator !== null) {
            return self::canAccessKnownIndicatorModule(
                $moduleKey,
                $moduleKey,
                $ssId,
                $user,
                $dynamicIndicator
            );
        }

        if ($user instanceof User && $user->hasRole(['admin', 'parametrizador'])) {
            return true;
        }

        if (! self::isGloballyEnabled($toggleModuleKey)) {
            return false;
        }

        if ($ssId === null) {
            if (! $user instanceof User) {
                return false;
            }
            $ssId = $user->cen_hie_dependency_id;
        }

        if (! $ssId) {
            return false;
        }

        if (self::resolveServicioSalud($ssId) === null) {
            return false;
        }

        return ServiceModuleToggle::isEnabled($ssId, $toggleModuleKey);
    }

    /**
     * Acceso a instrumentos registrados en INDICATOR_TYPE_MODULE_ROUTES o dinámicos por route.
     */
    private static function canAccessKnownIndicatorModule(
        string $indicatorRoute,
        string $toggleModuleKey,
        ?int $ssId,
        ?User $user,
        ?IndicatorType $knownIndicatorType = null,
    ): bool {
        if (! $user instanceof User) {
            return false;
        }

        $indicatorType = $knownIndicatorType;
        if ($indicatorType === null || (string) $indicatorType->route !== $indicatorRoute) {
            $indicatorType = self::indicatorTypeByRoute($indicatorRoute);
        }

        if ($indicatorType === null) {
            return false;
        }

        if (! $indicatorType->estado) {
            return false;
        }

        if ($user->hasRole(['admin', 'parametrizador'])) {
            return true;
        }

        if ($ssId === null) {
            $ssId = $user->cen_hie_dependency_id;
        }

        if (! $ssId) {
            return false;
        }

        if (self::resolveServicioSalud($ssId) === null) {
            return false;
        }

        if (! ServiceModuleToggle::isEnabled($ssId, $toggleModuleKey)) {
            return false;
        }

        if ($user->hasRole('control-gestion')) {
            return true;
        }

        if ($user->hasRole(['referente', 'visualizador'])) {
            return self::indicatorYearHabilitadoForSs($indicatorType, $ssId);
        }

        return true;
    }

    private static function indicatorYearHabilitadoForSs(IndicatorType $indicatorType, int $ssId): bool
    {
        $year = session('indicator_year');
        if (! $year instanceof Year) {
            return false;
        }

        $setKey = $ssId.':'.$year->id;
        if (! isset(self::$memo['habilitado'][$setKey])) {
            self::$memo['habilitado'][$setKey] = IndicatorTypeSetting::query()
                ->where('year_id', $year->id)
                ->where('cen_hie_dependency_id', $ssId)
                ->where('habilitado', true)
                ->pluck('indicator_type_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
        }

        return isset(self::$memo['habilitado'][$setKey][(int) $indicatorType->id]);
    }

    /**
     * Verifica si el modulo esta globalmente habilitado segun su fuente:
     * - indicator_types.estado para COMGES, ADP y Metas.
     * - config() para soporte, chat_ia, manual_usuario (ya actualizado por el bridge en produccion).
     *
     * Fallback a Voyager setting() cuando config() retorna el default de env()
     * (ej. en testing donde el bridge no pudo ejecutarse).
     */
    private static function isGloballyEnabled(string $moduleKey): bool
    {
        $indicatorRoute = self::INDICATOR_TYPE_MODULE_ROUTES[$moduleKey] ?? null;

        if ($indicatorRoute !== null) {
            $indicatorType = self::indicatorTypeByRoute($indicatorRoute);

            return $indicatorType !== null && $indicatorType->estado;
        }

        $configModules = ['soporte', 'chat_ia', 'manual_usuario'];
        if (in_array($moduleKey, $configModules, true)) {
            return app(ConfigurationService::class)->isRadioOn('module.'.$moduleKey.'_enabled');
        }

        return true;
    }

    private static function resolveServicioSalud(int $ssId): ?CenHieDependency
    {
        if (! array_key_exists($ssId, self::$memo['ss'])) {
            $ss = CenHieDependency::find($ssId);
            if (! $ss || ! $ss->habilitado || $ss->tipo_dependencia !== 'SERVICIO') {
                self::$memo['ss'][$ssId] = false;

                return null;
            }
            self::$memo['ss'][$ssId] = $ss;
        }

        $cached = self::$memo['ss'][$ssId];

        return $cached === false ? null : $cached;
    }

    private static function indicatorTypeByRoute(string $route): ?IndicatorType
    {
        if (! array_key_exists($route, self::$memo['indicators'])) {
            $found = IndicatorType::query()->where('route', $route)->first();
            self::$memo['indicators'][$route] = $found ?? false;
        }

        $cached = self::$memo['indicators'][$route];

        return $cached === false ? null : $cached;
    }
}
