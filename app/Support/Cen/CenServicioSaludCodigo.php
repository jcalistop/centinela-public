<?php

namespace App\Support\Cen;

use App\Enums\Cen\DependenciaTipo;
use App\Models\CenHieDependency;

/**
 * Normaliza códigos de Servicio de Salud entre CKAN (p. ej. «05») y cen_hie_dependencies (p. ej. «5»).
 */
final class CenServicioSaludCodigo
{
    /**
     * @return list<string>
     */
    public static function lookupCandidates(?string $catalogCodigo): array
    {
        $trimmed = CenApiGlosaNormalizer::trimGlosa($catalogCodigo);

        if ($trimmed === null) {
            return [];
        }

        $candidates = [$trimmed];

        if (preg_match('/^\d+$/', $trimmed) !== 1) {
            return $candidates;
        }

        $withoutLeadingZeros = (string) (int) $trimmed;

        if ($withoutLeadingZeros !== $trimmed) {
            $candidates[] = $withoutLeadingZeros;
        }

        if (strlen($withoutLeadingZeros) === 1) {
            $padded = str_pad($withoutLeadingZeros, 2, '0', STR_PAD_LEFT);

            if ($padded !== $trimmed) {
                $candidates[] = $padded;
            }
        }

        return array_values(array_unique($candidates));
    }

    public static function findDependency(?string $catalogCodigo): ?CenHieDependency
    {
        foreach (self::lookupCandidates($catalogCodigo) as $candidate) {
            $dependency = CenHieDependency::query()
                ->where('codigo', $candidate)
                ->where('tipo_dependencia', DependenciaTipo::Servicio->value)
                ->first();

            if ($dependency !== null) {
                return $dependency;
            }
        }

        return null;
    }

    /**
     * Código para filtro CKAN cuando el origen es cen_hie_dependencies.codigo (sync por SS).
     */
    public static function toApiFilterCodigo(?string $cenCodigo): ?string
    {
        $trimmed = CenApiGlosaNormalizer::trimGlosa($cenCodigo);

        if ($trimmed === null) {
            return null;
        }

        if (preg_match('/^\d+$/', $trimmed) !== 1) {
            return $trimmed;
        }

        $normalized = (string) (int) $trimmed;

        if (strlen($normalized) === 1) {
            return str_pad($normalized, 2, '0', STR_PAD_LEFT);
        }

        return $trimmed;
    }
}
