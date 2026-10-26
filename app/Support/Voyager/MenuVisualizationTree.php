<?php

declare(strict_types=1);

namespace App\Support\Voyager;

use App\Helpers\Helper;
use App\Models\MenuVisualization;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Árbol de instrumentos locales para el sidebar Voyager (SDD-174).
 */
final class MenuVisualizationTree
{
    private const CACHE_PREFIX = 'menu_visualization_tree:';

    private const TTL_SECONDS = 1800;

    /**
     * @return list<array{id: int|string, title: string, href: string, target: string, icon_class: string, active: bool, children: list<array<string, mixed>>}>
     */
    public static function adminMenuItems(): array
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->cen_hie_dependency_id) {
            return [];
        }

        $year = Helper::showCurrentYear();
        if (! $year instanceof Year) {
            return [];
        }

        $ssId = (int) $user->cen_hie_dependency_id;
        $yearId = (int) $year->id;

        return Cache::remember(
            self::cacheKey($ssId, $yearId),
            self::TTL_SECONDS,
            fn (): array => self::buildTree($ssId, $yearId)
        );
    }

    public static function invalidate(int $ssId, int $yearId): void
    {
        Cache::forget(self::cacheKey($ssId, $yearId));
    }

    public static function cacheKey(int $ssId, int $yearId): string
    {
        return self::CACHE_PREFIX.$ssId.':'.$yearId;
    }

    private static function itemHref(MenuVisualization $item): string
    {
        if (! $item->route || ! Route::has($item->route)) {
            return '';
        }

        return route($item->route, $item->id);
    }

    /**
     * @return list<array{id: int|string, title: string, href: string, target: string, icon_class: string, active: bool, children: list<array<string, mixed>>}>
     */
    private static function buildTree(int $ssId, int $yearId): array
    {
        $items = MenuVisualization::query()
            ->where('cen_hie_dependency_id', $ssId)
            ->where('year_id', $yearId)
            ->where('estado', true)
            ->orderBy('orden')
            ->get();

        $menu = [];

        foreach ($items as $item) {
            if (is_null($item->menu_superior)) {
                $menu[$item->id] = [
                    'id' => $item->id,
                    'title' => $item->nombre_menu,
                    'href' => self::itemHref($item),
                    'target' => '_self',
                    'icon_class' => 'voyager-dashboard',
                    'active' => false,
                    'children' => [],
                ];
            } elseif (! isset($menu[$item->menu_superior])) {
                $menu[$item->menu_superior] = [
                    'id' => $item->id,
                    'title' => $item->menu_superior,
                    'href' => '',
                    'target' => '_self',
                    'icon_class' => 'voyager-dashboard',
                    'active' => false,
                    'children' => [],
                ];
            }
        }

        foreach ($items as $item) {
            if (! is_null($item->menu_superior)) {
                $menu[$item->menu_superior]['children'][] = [
                    'id' => $item->id,
                    'title' => $item->nombre_menu,
                    'href' => self::itemHref($item),
                    'target' => '_self',
                    'icon_class' => $item->icon_class,
                    'active' => false,
                    'children' => [],
                ];
            }
        }

        return array_values($menu);
    }
}
