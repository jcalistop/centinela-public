<?php

namespace App\Models;

use App\Services\Dashboard\PublicSsDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cen_hie_dependency_id
 * @property string $module_key
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServiceModuleToggle extends Model
{
    protected $fillable = [
        'cen_hie_dependency_id',
        'module_key',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        $forgetPublicCache = static function (self $toggle): void {
            self::flushRequestMemo();

            if ($toggle->cen_hie_dependency_id === null) {
                return;
            }

            PublicSsDashboardService::forgetForSs((int) $toggle->cen_hie_dependency_id);
        };

        static::saved($forgetPublicCache);
        static::deleted($forgetPublicCache);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class);
    }

    /**
     * Voyager: scope en details de la relacion hasMany en Servicios de Salud (solo modulos habilitados).
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Memo request-scoped: mapa module_key => enabled por SS (SDD-174).
     *
     * @var array<int, array<string, bool>>
     */
    private static array $enabledBySs = [];

    /**
     * Limpia la memoización de toggles (tests / jobs).
     */
    public static function flushRequestMemo(): void
    {
        self::$enabledBySs = [];
    }

    /**
     * Verifica si un modulo esta habilitado para un SS especifico.
     * Si no existe registro (backward compatible), se asume habilitado.
     * Carga todos los toggles del SS en la primera consulta (evita N+1 en menú).
     */
    public static function isEnabled(int $ssId, string $moduleKey): bool
    {
        if (! array_key_exists($ssId, self::$enabledBySs)) {
            self::$enabledBySs[$ssId] = static::query()
                ->where('cen_hie_dependency_id', $ssId)
                ->pluck('enabled', 'module_key')
                ->map(fn ($enabled) => (bool) $enabled)
                ->all();
        }

        if (! array_key_exists($moduleKey, self::$enabledBySs[$ssId])) {
            return true;
        }

        return self::$enabledBySs[$ssId][$moduleKey];
    }

    /**
     * Retorna la lista de modulos conocidos desde config.
     *
     * @return array<int, string>
     */
    public static function knownModules(): array
    {
        return array_keys(config('modules.modules', []));
    }

    /**
     * Retorna el label de un modulo desde config.
     */
    public static function moduleLabel(string $moduleKey): string
    {
        return config('modules.modules.'.$moduleKey, $moduleKey);
    }
}
