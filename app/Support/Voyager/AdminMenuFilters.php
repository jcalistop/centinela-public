<?php

namespace App\Support\Voyager;

use App\Helpers\ModuleAccess;

/**
 * Ajustes al JSON del menú admin de Voyager (sidebar).
 *
 * La visibilidad del ítem Soporte la controla Voyager con el permiso `browse_soporte`.
 */
final class AdminMenuFilters
{
    private const SUPPORT_INDEX_ROUTE = 'centinela.support.index';

    /**
     * Quita la entrada Soporte cuando el módulo está deshabilitado (SDD-050).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function hideSupportWhenDisabled(array $items): array
    {
        if (ModuleAccess::canAccess('soporte')) {
            return $items;
        }

        return self::filterRecursive($items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function filterRecursive(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (($item['route'] ?? null) === self::SUPPORT_INDEX_ROUTE) {
                continue;
            }

            $children = $item['children'] ?? null;
            if (is_array($children) && $children !== []) {
                $item['children'] = self::filterRecursive($children);
            }

            $out[] = $item;
        }

        return $out;
    }
}
