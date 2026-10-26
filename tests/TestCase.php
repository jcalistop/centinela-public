<?php

namespace Tests;

use App\Helpers\ModuleAccess;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Support\Metas\MetSemaforoThresholds;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMinimalCenCenter;

/**
 * @method mixed mock(string $abstract, ?\Closure $mock = null)
 * @method mixed partialMock(string $abstract, ?\Closure $mock = null)
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use CreatesMinimalCenCenter;

    protected function setUp(): void
    {
        parent::setUp();

        // Evita ViteException en CI/local sin `npm run build` / `public/hot`
        // (p. ej. entry SDD-180 `metas-copy-image.js` vía @vite).
        $this->withoutVite();

        ModuleAccess::flushRequestMemo();
        MetSemaforoThresholds::flushResolved();

        // SDD-263: capa 1 exige estado=true. Las migraciones insertan instrumentos Off;
        // en tests el default usable es On (cada test puede apagar explícitamente).
        if (Schema::hasTable('indicator_types')) {
            IndicatorType::unguarded(fn () => IndicatorType::query()->update(['estado' => true]));
        }
    }

    /**
     * Fila `indicator_types` sembrada por migraciones para el slug de ruta dado.
     * Evita duplicar rutas en tests: binding HTTP/Livewire resuelve por esa columna.
     *
     * @param  array<string, mixed>  $attributes  Atributos a persistir (p. ej. `estado`).
     */
    protected function indicatorTypeByRoute(string $route = 'metas-18834', array $attributes = []): IndicatorType
    {
        return IndicatorType::unguarded(function () use ($route, $attributes): IndicatorType {
            $type = IndicatorType::query()->where('route', $route)->firstOrFail();
            if ($attributes !== []) {
                $type->update($attributes);
            }

            return $type->fresh();
        });
    }

    /**
     * Persiste etapa de ciclo Metas; `habilitado` se deriva en el modelo según la etapa.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function updateMetasEtapa(IndicatorTypeSetting $setting, string $etapa, array $extra = []): void
    {
        IndicatorTypeSetting::unguarded(fn () => $setting->update(array_merge([
            'etapa_vigente' => $etapa,
        ], $extra)));
    }
}
