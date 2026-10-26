<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class IndicatorType extends Model
{
    protected $fillable = [
        'clave_indicador',
        'nombre_indicador',
        'descripcion_indicador',
        'icon_class',
        'route',
        'view',
        'estado',
        'orden',
        'measurement_granularity',
    ];

    /**
     * @return array<int, string>
     */
    public static function configMetasRouteSlugs(): array
    {
        return config('centinela.metas_indicator_type_route_slugs', []);
    }

    /**
     * Opciones de granularidad: valor interno => etiqueta en formulario y vista detalle.
     *
     * @return array<string, string>
     */
    public static function measurementGranularityOptions(): array
    {
        return [
            'establishment' => 'Por establecimiento (Metas — Leyes 18.834 y 19.664)',
            'internal_unit' => 'Por unidad interna (Metas — Ley 20.707)',
            'health_service' => 'Por Servicio de Salud (Compromiso de Gestión)',
            'adp_cargo_establishment' => 'Por cargo/establecimiento (Alta Dirección Pública)',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function measurementGranularityValues(): array
    {
        return array_keys(self::measurementGranularityOptions());
    }

    public static function measurementGranularityLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return self::measurementGranularityOptions()[$value] ?? $value;
    }

    /**
     * Lista para `Rule::in` al actualizar: whitelist + ruta actual si fuese legacy.
     *
     * @return array<int, string>
     */
    public static function metasRouteSlugsForValidation(int $editingId): array
    {
        $allowed = self::configMetasRouteSlugs();
        $row = self::query()->find($editingId);
        if ($row?->route !== null && $row->route !== '' && ! in_array($row->route, $allowed, true)) {
            $allowed[] = $row->route;
        }

        return array_values(array_unique($allowed));
    }

    protected $casts = [
        'estado' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('indicator_type_routes');
        });

        static::deleted(function (): void {
            Cache::forget('indicator_type_routes');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'route';
    }

    public function usesInternalUnitMeasurement(): bool
    {
        return $this->measurement_granularity === 'internal_unit';
    }

    /**
     * Etiqueta corta de ley para títulos Metas (ej. «Ley 18.834»).
     */
    public function metasLeyLabel(): ?string
    {
        $fromRoute = match ($this->route) {
            'metas-18834' => 'Ley 18.834',
            'metas-19664' => 'Ley 19.664',
            'metas-20707' => 'Ley 20.707',
            default => null,
        };

        if ($fromRoute !== null) {
            return $fromRoute;
        }

        if (preg_match('/Ley\s+([\d.]+)/i', (string) $this->clave_indicador, $matches) === 1) {
            return 'Ley '.$matches[1];
        }

        return null;
    }

    public function indicatorTypeSettings(): HasMany
    {
        return $this->hasMany(IndicatorTypeSetting::class);
    }

    public function indicatorTypeGlobalSettings(): HasMany
    {
        return $this->hasMany(IndicatorTypeGlobalSetting::class);
    }

    public function metIndicators(): HasMany
    {
        return $this->hasMany(MetIndicator::class);
    }
}
