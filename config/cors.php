<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * Lista separada por comas en CORS_ALLOWED_ORIGINS.
     * Producción (SDD-248 / S-04): sin fallback `*` — lista vacía si no hay env.
     * Otros entornos: `*` si no está definida (comportamiento histórico local).
     */
    'allowed_origins' => ($cors = env('CORS_ALLOWED_ORIGINS')) && trim((string) $cors) !== ''
        ? array_values(array_filter(array_map(static fn (string $v): string => trim($v), explode(',', (string) $cors))))
        : ((string) env('APP_ENV', 'production') === 'production' ? [] : ['*']),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
