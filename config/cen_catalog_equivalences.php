<?php

use App\Support\Cen\CenCatalogGlosaResolver;

/**
 * Equivalencias API CKAN → valores canónicos persistidos (cen_centers / cen_addresses).
 *
 * @see CenCatalogGlosaResolver
 * @see SDD-083
 */
return [

    'estado_funcionamiento' => [
        'collapse' => [
            'Cerrado' => [
                'Cerrado',
                'Cierre Definitivo',
                'Cierre Temporal',
                'CIERRE DEFINITIVO',
                'CIERRE TEMPORAL',
            ],
        ],
    ],

    'pertenencia_snss' => [
        'alias' => [
            'Perteneciente al Sistema Nacional de Servicios de Salud' => 'Perteneciente',
        ],
    ],

];
