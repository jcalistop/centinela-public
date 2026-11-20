<?php

declare(strict_types=1);

namespace App\Support\Configuration;

/**
 * Identidad pública de la instalación (SDD-338).
 * Distinto del SS de tenancy del usuario (`cen_hie_dependency_id`).
 */
final class InstanceIdentity
{
    public const DEFAULT_DISPLAY_NAME = 'SS de prueba';

    public const DEFAULT_UNIT_NAME = 'Departamento de Control de Gestión';

    public static function displayName(): string
    {
        $value = trim((string) config('centinela.instance.ss_display_name', ''));

        return $value !== '' ? $value : self::DEFAULT_DISPLAY_NAME;
    }

    public static function unitName(): string
    {
        $value = trim((string) config('centinela.instance.unit_name', ''));

        return $value !== '' ? $value : self::DEFAULT_UNIT_NAME;
    }

    /**
     * URL institucional; cadena vacía si no hay URL usable.
     */
    public static function url(): string
    {
        $value = trim((string) config('centinela.instance.ss_url', ''));

        if ($value === '') {
            return '';
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $value;
    }
}
