<?php

namespace App\Support\Metas;

use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\Year;
use Illuminate\Support\Facades\Log;

/**
 * Umbrales de semáforo (badges / bordes) de Metas. Conviven dos semáforos distintos:
 *
 * 1. Cumplimiento global del establecimiento (métodos de instancia): insuficiente / parcial /
 *    completo, según `semaforo_verde_min` / `semaforo_amarillo_min` del instrumento en el año
 *    (con herencia por `route` si la fila o la columna es NULL).
 * 2. Cumplimiento por fila del indicador (métodos estáticos `*ForIndicatorCompliance`): comunica el
 *    aporte ponderado (0 / proporcional / 100 % de la ponderación) según los umbrales de la ficha
 *    del indicador. No usa el piso del semáforo global (SDD-186).
 */
final class MetSemaforoThresholds
{
    private function __construct(
        public readonly string $greenLegend,
        public readonly string $yellowLegend,
        public readonly string $redLegend,
        public readonly float $greenMinPercent,
        public readonly float $yellowMinPercent,
    ) {}

    /**
     * Defaults por ruta (paridad histórica). Producción usa {@see forIndicatorType()}.
     */
    public static function fromIndicatorRoute(string $route): self
    {
        return self::defaultsForRoute($route);
    }

    public static function defaultsForRoute(string $route): self
    {
        return match ($route) {
            'metas-18834' => self::makeFromMins(100.0, 75.0),
            'metas-19664', 'metas-20707' => self::makeFromMins(95.0, 75.0),
            default => self::unknownRouteDefaults($route),
        };
    }

    public static function forIndicatorType(int $indicatorTypeId, ?string $route = null, ?int $yearId = null): self
    {
        $yearId ??= self::yearIdFromSession();
        $cacheKey = 'metas.semaforo.'.$indicatorTypeId.'.'.($yearId ?? 0);

        $request = request();
        if ($request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        $route ??= (string) (IndicatorType::query()->whereKey($indicatorTypeId)->value('route') ?? '');
        $defaults = self::defaultsForRoute($route);

        $setting = $yearId !== null
            ? IndicatorTypeGlobalSetting::resolveFor($indicatorTypeId, $yearId)
            : null;

        $green = $setting?->semaforo_verde_min !== null
            ? (float) $setting->semaforo_verde_min
            : $defaults->greenMinPercent;
        $yellow = $setting?->semaforo_amarillo_min !== null
            ? (float) $setting->semaforo_amarillo_min
            : $defaults->yellowMinPercent;

        $resolved = self::makeFromMins($green, $yellow);
        $request->attributes->set($cacheKey, $resolved);

        return $resolved;
    }

    public static function forgetResolved(int $indicatorTypeId, ?int $yearId = null): void
    {
        $yearId ??= self::yearIdFromSession();
        request()->attributes->remove('metas.semaforo.'.$indicatorTypeId.'.'.($yearId ?? 0));
    }

    public static function flushResolved(): void
    {
        $request = request();
        foreach (array_keys($request->attributes->all()) as $key) {
            if (is_string($key) && str_starts_with($key, 'metas.semaforo.')) {
                $request->attributes->remove($key);
            }
        }
    }

    public function labelClass(?float $cumplimientoPercent): string
    {
        return self::labelClassForTier($this->tier($cumplimientoPercent));
    }

    /**
     * Umbral verde para cumplimiento global agregado (pie de tablas / tarjetas de resumen).
     */
    public function globalGreenUmbral(): float
    {
        return $this->greenMinPercent;
    }

    /**
     * Semáforo por fila sobre el cumplimiento lineal, según el aporte ponderado del indicador:
     * verde al alcanzar el umbral superior (aporta 100 %), rojo cuando el aporte es 0 (bajo el
     * umbral inferior o sin cumplimiento), amarillo cuando el aporte es proporcional (SDD-186).
     */
    public static function labelClassForIndicatorCompliance(
        ?float $cumplimientoPercent,
        ?float $umbralSuperior = null,
        ?float $umbralInferior = null,
    ): string {
        return self::labelClassForTier(
            self::tierForIndicatorCompliance($cumplimientoPercent, $umbralSuperior, $umbralInferior),
        );
    }

    public static function borderColorForIndicatorCompliance(
        ?float $cumplimientoPercent,
        ?float $umbralSuperior = null,
        ?float $umbralInferior = null,
    ): string {
        return self::borderColorForTier(
            self::tierForIndicatorCompliance($cumplimientoPercent, $umbralSuperior, $umbralInferior),
        );
    }

    /**
     * @param  'indicator'|'global'|'mixed'  $context
     * @return array{
     *     green: array{range: string, label: string},
     *     yellow: array{range: string, label: string},
     *     red: array{range: string, label: string},
     *     note: ?string
     * }
     */
    public static function resolveLegend(
        string $context = 'global',
        ?float $umbralSuperior = null,
        ?string $route = null,
        ?float $umbralInferior = null,
        ?float $umbralAmarillo = null,
    ): array {
        if ($context === 'mixed') {
            return [
                'green' => [
                    'range' => '',
                    'label' => 'Cumplimiento satisfactorio',
                ],
                'yellow' => [
                    'range' => '',
                    'label' => 'Cumplimiento parcial',
                ],
                'red' => [
                    'range' => '',
                    'label' => 'Cumplimiento insuficiente',
                ],
                'note' => 'El color refleja el cumplimiento respecto de los umbrales de cada indicador (ver ficha). Solo los indicadores en convenio aportan al cumplimiento global.',
            ];
        }

        if ($context === 'indicator') {
            $umbral = $umbralSuperior
                ?? ($route !== null ? self::fromIndicatorRoute($route)->greenMinPercent : 100.0);
            $ranges = self::indicatorRangesForUmbrales($umbral, $umbralInferior ?? 0.0);

            return [
                'green' => ['range' => $ranges['green'], 'label' => 'Cumplimiento satisfactorio'],
                'yellow' => ['range' => $ranges['yellow'], 'label' => 'Cumplimiento parcial'],
                'red' => ['range' => $ranges['red'], 'label' => 'Cumplimiento insuficiente'],
                'note' => null,
            ];
        }

        $scheme = self::schemeForGlobalLegend($umbralSuperior, $umbralAmarillo, $route);
        $ranges = self::numericRangesForUmbral($scheme->greenMinPercent, $scheme->yellowMinPercent);

        return [
            'green' => ['range' => $ranges['green'], 'label' => 'Cumplimiento global satisfactorio'],
            'yellow' => ['range' => $ranges['yellow'], 'label' => 'Cumplimiento parcial'],
            'red' => ['range' => $ranges['red'], 'label' => 'Cumplimiento insuficiente'],
            'note' => null,
        ];
    }

    public function globalLegendFootnote(): string
    {
        $legend = self::resolveLegend(
            'global',
            $this->greenMinPercent,
            null,
            null,
            $this->yellowMinPercent,
        );

        return sprintf(
            'Cumplimiento global: %s verde, %s amarillo, %s rojo.',
            $legend['green']['range'],
            $legend['yellow']['range'],
            $legend['red']['range'],
        );
    }

    /**
     * Rangos del semáforo por fila: el rojo nace del umbral inferior del indicador (aporte 0),
     * nunca de un piso fijo.
     *
     * @return array{green: string, yellow: string, red: string}
     */
    private static function indicatorRangesForUmbrales(float $umbralSuperior, float $umbralInferior): array
    {
        $superiorLabel = self::formatPercentLabel($umbralSuperior);
        $greenRange = $umbralSuperior >= 100.0 ? '100%' : '≥'.$superiorLabel;

        if ($umbralInferior <= 0.0) {
            return [
                'green' => $greenRange,
                'yellow' => '<'.$superiorLabel,
                'red' => '0%',
            ];
        }

        $inferiorLabel = self::formatPercentLabel($umbralInferior);

        return [
            'green' => $greenRange,
            'yellow' => $inferiorLabel.' a <'.$superiorLabel,
            'red' => '<'.$inferiorLabel,
        ];
    }

    /**
     * Rangos del semáforo de cumplimiento global, derivados de ambos mínimos del instrumento.
     *
     * @return array{green: string, yellow: string, red: string}
     */
    private static function numericRangesForUmbral(float $greenMin, float $yellowMin): array
    {
        $greenLabel = self::formatPercentLabel($greenMin);
        $yellowLabel = self::formatPercentLabel($yellowMin);
        $greenIsInteger = abs($greenMin - round($greenMin)) < 0.001;
        $yellowIsInteger = abs($yellowMin - round($yellowMin)) < 0.001;

        $greenRange = $greenMin >= 100.0 ? '100%' : '≥'.$greenLabel;

        if ($greenIsInteger && $yellowIsInteger) {
            $yellowRange = $yellowLabel.' a '.((int) round($greenMin) - 1).'%';
        } else {
            $yellowRange = $yellowLabel.' a <'.$greenLabel;
        }

        return [
            'green' => $greenRange,
            'yellow' => $yellowRange,
            'red' => '<'.$yellowLabel,
        ];
    }

    public function borderColor(?float $cumplimientoPercent): string
    {
        return self::borderColorForTier($this->tier($cumplimientoPercent));
    }

    /**
     * @return 'green'|'yellow'|'red'|null
     */
    public function tier(?float $cumplimientoPercent): ?string
    {
        if ($cumplimientoPercent === null) {
            return null;
        }

        if ($cumplimientoPercent >= $this->greenMinPercent) {
            return 'green';
        }

        if ($cumplimientoPercent >= $this->yellowMinPercent) {
            return 'yellow';
        }

        return 'red';
    }

    /**
     * @return 'green'|'yellow'|'red'|null
     */
    private static function tierForIndicatorCompliance(
        ?float $cumplimientoPercent,
        ?float $umbralSuperior = null,
        ?float $umbralInferior = null,
    ): ?string {
        if ($cumplimientoPercent === null) {
            return null;
        }

        if ($cumplimientoPercent >= ($umbralSuperior ?? 100.0)) {
            return 'green';
        }

        $aporteEsNulo = $cumplimientoPercent <= 0.0
            || ($umbralInferior !== null && $cumplimientoPercent < $umbralInferior);

        return $aporteEsNulo ? 'red' : 'yellow';
    }

    /**
     * @param  'green'|'yellow'|'red'|null  $tier
     */
    private static function labelClassForTier(?string $tier): string
    {
        return match ($tier) {
            'green' => 'success',
            'yellow' => 'warning',
            'red' => 'danger',
            default => 'default',
        };
    }

    /**
     * @param  'green'|'yellow'|'red'|null  $tier
     */
    private static function borderColorForTier(?string $tier): string
    {
        return match ($tier) {
            'green' => '#5cb85c',
            'yellow' => '#f0ad4e',
            'red' => '#d9534f',
            default => '#b0b0b0',
        };
    }

    private static function formatPercentLabel(float $value): string
    {
        if (abs($value - round($value)) < 0.001) {
            return (string) (int) round($value).'%';
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    }

    private static function makeFromMins(float $greenMin, float $yellowMin): self
    {
        $ranges = self::numericRangesForUmbral($greenMin, $yellowMin);

        return new self(
            greenLegend: $ranges['green'],
            yellowLegend: $ranges['yellow'],
            redLegend: $ranges['red'],
            greenMinPercent: $greenMin,
            yellowMinPercent: $yellowMin,
        );
    }

    private static function unknownRouteDefaults(string $route): self
    {
        Log::warning('Metas semáforo global: ruta de instrumento desconocida; se usan umbrales 95/75.', [
            'route' => $route,
        ]);

        return self::makeFromMins(95.0, 75.0);
    }

    private static function schemeForGlobalLegend(?float $umbralSuperior, ?float $umbralAmarillo, ?string $route): self
    {
        if ($umbralSuperior !== null && $umbralAmarillo !== null) {
            return self::makeFromMins($umbralSuperior, $umbralAmarillo);
        }

        $defaults = $route !== null
            ? self::fromIndicatorRoute($route)
            : self::makeFromMins(100.0, 75.0);

        return self::makeFromMins(
            $umbralSuperior ?? $defaults->greenMinPercent,
            $umbralAmarillo ?? $defaults->yellowMinPercent,
        );
    }

    private static function yearIdFromSession(): ?int
    {
        $year = session('indicator_year');

        return $year instanceof Year ? $year->id : null;
    }
}
