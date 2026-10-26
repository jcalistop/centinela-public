<?php

namespace App\Support\Metas;

use App\Models\MetIndicator;
use App\Services\Metas\MetIndicatorPeriodicityResolver;

class MetIndicatorMiniFichaPresenter
{
    public function __construct(
        private MetIndicatorPeriodicityResolver $periodicityResolver,
    ) {}

    /**
     * Resumen compacto del indicador para el modal «Ver datos».
     *
     * @return array{
     *     periodicidad: string,
     *     unidad_medida: ?string,
     *     decimales: int,
     *     decimales_modo: ?string,
     *     meta_nacional: float,
     *     meta_efectiva: float,
     *     meta_operador: string,
     *     comprometido: ?bool,
     *     objetivo: ?string,
     *     formula_expression: ?string
     * }
     */
    public function forDataModal(
        MetIndicator $indicator,
        int $cenHieDependencyId,
        int $yearId,
        float $metaNacional,
        float $metaEfectiva,
        ?bool $comprometido = null,
    ): array {
        $periodicidad = $this->periodicityResolver
            ->resolveForCarga($indicator, $cenHieDependencyId, $yearId)
            ->value;

        $objetivo = ObjetivoHtml::toPlainExcerpt($indicator->objetivo, 220);

        return [
            'periodicidad' => $periodicidad,
            'unidad_medida' => $indicator->unidad_medida,
            'decimales' => (int) ($indicator->decimales ?? 2),
            'decimales_modo' => $indicator->decimales_modo?->value,
            'meta_nacional' => $metaNacional,
            'meta_efectiva' => $metaEfectiva,
            'meta_operador' => $indicator->meta_operador?->value ?? '>=',
            'comprometido' => $comprometido,
            'objetivo' => $objetivo,
            'formula_expression' => $indicator->formula?->description,
        ];
    }
}
