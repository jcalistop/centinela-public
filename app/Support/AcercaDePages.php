<?php

namespace App\Support;

class AcercaDePages
{
    /**
     * @var array<string, array{file: string, title: string, nav_label: string, icon: string, nav_label_html?: bool}>
     */
    public const PAGES = [
        'resumen' => [
            'file' => '01-resumen.md',
            'title' => 'Resumen',
            'nav_label' => 'Resumen',
            'icon' => 'voyager-question',
        ],
        'modulos' => [
            'file' => '02-modulos.md',
            'title' => 'Módulos de la plataforma',
            'nav_label' => 'Módulos',
            'icon' => 'voyager-list',
        ],
        'roles' => [
            'file' => '03-roles-y-contexto.md',
            'title' => 'Roles y contexto de operación',
            'nav_label' => 'Roles',
            'icon' => 'voyager-people',
        ],
        'ciclo-comges' => [
            'file' => '04-ciclo-comges.md',
            'title' => 'Ciclo COMGES',
            'nav_label' => 'Ciclo COMGES',
            'icon' => 'voyager-refresh',
        ],
        'ciclo-metas' => [
            'file' => '05-ciclo-metas.md',
            'title' => 'Ciclo Metas',
            'nav_label' => 'Ciclo Metas',
            'icon' => 'voyager-refresh',
        ],
        'adopcion' => [
            'file' => '06-adopcion.md',
            'title' => 'Adopción',
            'nav_label' => 'Adopción',
            'icon' => 'voyager-world',
        ],
        'tecnica' => [
            'file' => '07-informacion-tecnica.md',
            'title' => 'Información técnica',
            'nav_label' => 'Información técnica',
            'icon' => 'voyager-settings',
        ],
    ];

    /**
     * @return array<int, array{slug: string, label: string, route: string, icon: string, label_html?: bool}>
     */
    public static function navigation(): array
    {
        $pages = [];

        foreach (self::PAGES as $slug => $config) {
            $pages[] = [
                'slug' => $slug,
                'label' => $config['nav_label'],
                'label_html' => $config['nav_label_html'] ?? false,
                'route' => $slug === 'resumen'
                    ? route('acerca-de.index')
                    : route('acerca-de.'.$slug),
                'icon' => $config['icon'],
            ];
        }

        return $pages;
    }

    public static function iconFor(string $slug): string
    {
        return self::PAGES[$slug]['icon'] ?? 'voyager-question';
    }
}
