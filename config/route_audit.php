<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rutas excluidas del informe de huecos
    |--------------------------------------------------------------------------
    |
    | Prefijos de nombre o subcadenas de URI que no aportan al análisis de
    | cobertura (herramientas de desarrollo, Livewire interno, etc.).
    |
    */
    'exclude_route_name_prefixes' => [
        'livewire.',
        'ignition.',
        'horizon.',
        'debugbar.',
        'clockwork.',
    ],

    'exclude_uri_contains' => [
        'livewire/',
        '_ignition/',
        'horizon/',
        'debugbar/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Clasificación heurística de riesgo
    |--------------------------------------------------------------------------
    |
    | Se usa para triage y para --fail-on-high-gaps (solo nivel "high").
    |
    */
    'risk_high_route_name_prefixes' => [
        'centinela.',
        'adp.',
        'comges.',
        'metas.',
        'user-manual.',
        'download-file',
    ],

    /*
    | Rutas AJAX auxiliares bajo /admin: se auditan como media si no hay prueba directa.
    */
    'risk_medium_route_name_extra_prefixes' => [
        'api.provinces',
        'api.communes',
    ],

    'risk_high_uri_prefixes' => [
        'admin/system/',
        'download/',
        'storage/settings/',
        'storage/users/',
        'api/schedule/',
        'api/admin/token',
        'api/health',
    ],

    'risk_medium_route_name_prefixes' => [
        'voyager.',
    ],

    /*
    | Si el nombre de ruta comienza por uno de estos segmentos (antes del primer punto
    | adicional) y existe cualquier route('segmento. en tests/, se considera evidencia
    | de cobertura a nivel módulo (heurística; revisar huecos finos a mano).
    */
    'module_evidence_first_segments' => [
        'adp',
        'comges',
        'metas',
        'user-manual',
        'centinela',
    ],

];
