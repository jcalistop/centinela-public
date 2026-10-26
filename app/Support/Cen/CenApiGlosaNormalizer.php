<?php

namespace App\Support\Cen;

/**
 * Normaliza glosas CKAN (datos.gob.cl) a valores persistidos en cen_centers / cen_addresses.
 * Incluye mapa legacy→API para migraciones de backfill.
 */
final class CenApiGlosaNormalizer
{
    /** @var array<string, string> */
    private const LEGACY_ESTADO_FUNCIONAMIENTO = [
        'OPERACION HABITUAL' => 'Vigente en Operación Habitual',
        'OPERACION TRANSITORIA' => 'Vigente en Operación Transitoria',
        'CIERRE TEMPORAL' => 'Cierre Temporal',
        'CIERRE DEFINITIVO' => 'Cierre Definitivo',
    ];

    /** @var array<string, string> */
    private const LEGACY_VIA = [
        'AVENIDA' => 'Avenida',
        'CALLE' => 'Calle',
        'CAMINO' => 'Camino',
        'CARRETERA' => 'Carretera',
        'OTRO' => 'Otro',
        'PASAJE' => 'Pasaje',
        'SECTOR' => 'Sector',
        'PENDIENTE' => 'Pendiente',
    ];

    /** @var array<string, string> */
    private const LEGACY_NIVEL_ATENCION = [
        'PRIMARIO' => 'Primario',
        'SECUNDARIO' => 'Secundario',
        'TERCIARIO' => 'Terciario',
        'PENDIENTE' => 'Pendiente',
        'NO APLICA' => 'No Aplica',
    ];

    /** @var array<string, string> */
    private const LEGACY_TIENE_URGENCIA = [
        'SI' => 'Sí',
        'NO' => 'No',
        'NO APLICA' => 'No Aplica',
        'PENDIENTE' => 'Pendiente',
    ];

    /** @var array<string, string> */
    private const LEGACY_NIVEL_COMPLEJIDAD = [
        'ALTA' => 'Alta',
        'MEDIANA' => 'Mediana',
        'BAJA' => 'Baja',
        'NO APLICA' => 'No Aplica',
        'PENDIENTE' => 'Pendiente',
    ];

    /** @var array<string, string> */
    private const LEGACY_TIPO_ATENCION = [
        'ABIERTA' => 'Abierta',
        'CERRADA' => 'Cerrada',
        'ABIERTA Y CERRADA' => 'Abierta y Cerrada',
        'NO APLICA' => 'No Aplica',
        'PENDIENTE' => 'Pendiente',
    ];

    /** @var array<string, string> */
    private const LEGACY_TIPO_SAPU = [
        'CORTO' => 'Corto',
        'VERANO' => 'Verano',
        'LARGO' => 'Largo',
        'AVANZADO' => 'Avanzado',
        'PENDIENTE' => 'Pendiente',
        'NO APLICA' => 'No Aplica',
        '' => 'No Aplica',
    ];

    /** @var array<string, string> */
    private const LEGACY_PERTENENCIA = [
        'NO PERTENECIENTE' => 'No Perteneciente',
        'PERTENECIENTE' => 'Perteneciente',
        'NO APLICA' => 'No Aplica',
    ];

    /** Glosas CKAN vigentes no cubiertas por el mapa legacy UPPER. */
    private const API_ESTADO_FUNCIONAMIENTO = [
        'Cerrado' => 'Cerrado',
    ];

    private const API_PERTENENCIA_SNSS = [
        'Perteneciente al Sistema Nacional de Servicios de Salud' => 'Perteneciente',
    ];

    public static function trimGlosa(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function estadoFuncionamiento(?string $value): ?string
    {
        $mapped = self::map($value, self::LEGACY_ESTADO_FUNCIONAMIENTO, self::API_ESTADO_FUNCIONAMIENTO, normalizeCaseInsensitive: true);

        return $mapped !== null ? self::collapseWhitespacePassthrough($mapped) : null;
    }

    public static function via(?string $value): ?string
    {
        return self::map($value, self::LEGACY_VIA);
    }

    public static function nivelAtencion(?string $value): ?string
    {
        return self::map($value, self::LEGACY_NIVEL_ATENCION, normalizeCaseInsensitive: true);
    }

    public static function tieneUrgencia(?string $value): ?string
    {
        return self::map($value, self::LEGACY_TIENE_URGENCIA, normalizeCaseInsensitive: true);
    }

    public static function nivelComplejidad(?string $value): ?string
    {
        return self::map($value, self::LEGACY_NIVEL_COMPLEJIDAD, normalizeCaseInsensitive: true);
    }

    public static function tipoAtencion(?string $value): ?string
    {
        return self::map($value, self::LEGACY_TIPO_ATENCION, normalizeCaseInsensitive: true);
    }

    public static function tipoSapu(?string $value): ?string
    {
        return self::map($value, self::LEGACY_TIPO_SAPU, normalizeCaseInsensitive: true);
    }

    public static function pertenenciaSnss(?string $value): ?string
    {
        return self::map($value, self::LEGACY_PERTENENCIA, self::API_PERTENENCIA_SNSS);
    }

    /**
     * Campos que ya usan glosa API en enums (tipo, scope, certificación, etc.).
     */
    public static function passthrough(?string $value): ?string
    {
        return self::trimGlosa($value);
    }

    /**
     * Passthrough con espacios múltiples colapsados a uno (variabilidad CKAN).
     */
    public static function collapseWhitespacePassthrough(?string $value): ?string
    {
        $trimmed = self::trimGlosa($value);

        if ($trimmed === null) {
            return null;
        }

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }

    /**
     * @param  array<string, string>  $legacyMap
     * @param  array<string, string>  $apiMap
     */
    private static function map(
        ?string $value,
        array $legacyMap,
        array $apiMap = [],
        bool $normalizeCaseInsensitive = false,
    ): ?string {
        $trimmed = self::trimGlosa($value);

        if ($trimmed === null) {
            return null;
        }

        if (isset($apiMap[$trimmed])) {
            return $apiMap[$trimmed];
        }

        if (isset($legacyMap[$trimmed])) {
            return $legacyMap[$trimmed];
        }

        if ($normalizeCaseInsensitive) {
            $upper = mb_strtoupper($trimmed, 'UTF-8');

            foreach ($legacyMap as $legacyKey => $apiGlosa) {
                if (mb_strtoupper($legacyKey, 'UTF-8') === $upper) {
                    return $apiGlosa;
                }
            }
        }

        return $trimmed;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function legacyColumnMaps(): array
    {
        return [
            'estado_funcionamiento' => self::LEGACY_ESTADO_FUNCIONAMIENTO,
            'nivel_atencion' => self::LEGACY_NIVEL_ATENCION,
            'tiene_urgencia' => self::LEGACY_TIENE_URGENCIA,
            'tipo_sapu' => self::LEGACY_TIPO_SAPU,
            'nivel_complejidad' => self::LEGACY_NIVEL_COMPLEJIDAD,
            'tipo_atencion' => self::LEGACY_TIPO_ATENCION,
            'pertenencia_snss' => self::LEGACY_PERTENENCIA,
        ];
    }
}
