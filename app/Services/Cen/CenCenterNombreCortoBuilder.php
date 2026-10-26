<?php

namespace App\Services\Cen;

/**
 * Reglas extraídas de database/migrations/2026_05_19_140000_update_direccion_servicio_cen_centers_nombre_corto.php
 */
final class CenCenterNombreCortoBuilder
{
    private const OFFICIAL_PREFIX = 'Actividades gestionadas por la Dirección del Servicio para apoyo de la Red (';

    private const MAX_LENGTH = 120;

    /**
     * Orden: frase más larga primero.
     *
     * @var array<string, string>
     */
    private const ABBREVIATIONS = [
        'Centro Comunitario de Salud Familiar' => 'CECOSF',
        'Centro de Salud Familiar' => 'CESFAM',
        'Posta de Salud Rural' => 'PSR',
    ];

    public function fromEstablecimientoGlosa(string $establecimientoGlosa): string
    {
        $nombreOficial = $this->normalizedOfficialName($establecimientoGlosa);
        $fromDireccion = $this->nombreCortoFromDireccionPrefix($nombreOficial);

        if ($fromDireccion !== null) {
            return $this->truncate($fromDireccion);
        }

        return $this->truncate($this->applyAbbreviations($nombreOficial));
    }

    private function nombreCortoFromDireccionPrefix(string $nombreOficial): ?string
    {
        if (! str_starts_with($nombreOficial, self::OFFICIAL_PREFIX)) {
            return null;
        }

        $fragment = substr($nombreOficial, strlen(self::OFFICIAL_PREFIX));
        $fragment = rtrim($fragment, ')');
        $servicio = preg_replace('/^S\.S\s+/i', '', $fragment) ?? $fragment;

        if ($servicio === '') {
            return null;
        }

        return 'Dirección SS '.$servicio;
    }

    private function normalizedOfficialName(string $nombreOficial): string
    {
        if (! str_starts_with($nombreOficial, self::OFFICIAL_PREFIX)) {
            return trim($nombreOficial);
        }

        if (str_ends_with($nombreOficial, ')')) {
            return $nombreOficial;
        }

        return $nombreOficial.')';
    }

    private function applyAbbreviations(string $text): string
    {
        foreach (self::ABBREVIATIONS as $from => $to) {
            $text = str_replace($from, $to, $text);
        }

        return $text;
    }

    private function truncate(string $text): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, self::MAX_LENGTH - 1)).'…';
    }
}
