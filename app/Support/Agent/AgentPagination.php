<?php

namespace App\Support\Agent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AgentPagination
{
    /**
     * Filtro válido sin filas: el código consultado existe en el catálogo, el año no tiene datos.
     */
    public const EMPTY_REASON_SIN_DATOS = 'sin_datos';

    /**
     * Código fuera del catálogo Centinela (no es «sin datos» ni error de validación).
     */
    public const EMPTY_REASON_AUSENTE = 'ausente';

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  list<array<string, mixed>>  $items
     * @param  string|null  $emptyReason  Se agrega a `meta` solo cuando `total` es 0.
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|string>}
     */
    public static function wrap(LengthAwarePaginator $paginator, array $items, ?string $emptyReason = null): array
    {
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        if ($emptyReason !== null && $paginator->total() === 0) {
            $meta['empty_reason'] = $emptyReason;
        }

        return [
            'data' => $items,
            'meta' => $meta,
        ];
    }

    /**
     * Sobre vacío para un código fuera del catálogo (`ausente`), con echo del filtro pedido.
     *
     * @param  array<string, mixed>  $echo
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public static function ausente(int $perPage, array $echo = []): array
    {
        return [
            'data' => [],
            'meta' => array_merge([
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'empty_reason' => self::EMPTY_REASON_AUSENTE,
            ], $echo),
        ];
    }

    public static function perPage(mixed $raw, int $default = 25, int $max = 100): int
    {
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            return $default;
        }

        return min($value, $max);
    }

    public static function page(mixed $raw, int $default = 1): int
    {
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            return $default;
        }

        return $value;
    }
}
