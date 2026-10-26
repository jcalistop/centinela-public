<?php

namespace App\Support\Cen;

use App\Enums\Cen\CenCenterAdmDependency;
use App\Enums\Cen\CenCenterCertification;
use App\Enums\Cen\CenCenterEmergencyType;
use App\Enums\Cen\CenCenterProviderType;
use App\Enums\Cen\CenCenterScope;
use App\Enums\Cen\CenCenterType;
use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Cen\CentroNivelAtencion;
use App\Enums\Cen\CentroNivelComplejidad;
use App\Enums\Cen\CentroPertenenciaSnss;
use App\Enums\Cen\CentroTieneUrgencia;
use App\Enums\Cen\CentroTipoAtencion;
use App\Enums\Cen\CentroTipoSapu;
use App\Enums\Cen\DireccionVia;
use App\Services\Cen\CenCatalogGlosaEquivalenceService;
use App\Support\Cen\Dto\CenCatalogGlosaResolution;
use BackedEnum;

final class CenCatalogGlosaResolver
{
    /** @var array<string, array{targets: array<string, string>, rules: array<string, string>}>|null */
    private static ?array $equivalenceLookups = null;

    /**
     * @var array<string, callable(?string): ?string>
     */
    private const SYNTAX_NORMALIZERS = [
        'estado_funcionamiento' => [CenApiGlosaNormalizer::class, 'estadoFuncionamiento'],
        'pertenencia_snss' => [CenApiGlosaNormalizer::class, 'pertenenciaSnss'],
        'cen_type' => [CenApiGlosaNormalizer::class, 'collapseWhitespacePassthrough'],
        'cen_scope' => [CenApiGlosaNormalizer::class, 'collapseWhitespacePassthrough'],
        'cen_certification' => [CenApiGlosaNormalizer::class, 'collapseWhitespacePassthrough'],
        'cen_adm_dependency' => [CenApiGlosaNormalizer::class, 'collapseWhitespacePassthrough'],
        'nivel_atencion' => [CenApiGlosaNormalizer::class, 'nivelAtencion'],
        'tiene_urgencia' => [CenApiGlosaNormalizer::class, 'tieneUrgencia'],
        'cen_emergency_type' => [CenApiGlosaNormalizer::class, 'collapseWhitespacePassthrough'],
        'tipo_sapu' => [CenApiGlosaNormalizer::class, 'tipoSapu'],
        'cen_provider_type' => [CenApiGlosaNormalizer::class, 'collapseWhitespacePassthrough'],
        'nivel_complejidad' => [CenApiGlosaNormalizer::class, 'nivelComplejidad'],
        'tipo_atencion' => [CenApiGlosaNormalizer::class, 'tipoAtencion'],
        'via' => [CenApiGlosaNormalizer::class, 'via'],
    ];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    public const FIELD_LABELS = [
        'estado_funcionamiento' => 'Estado de funcionamiento',
        'pertenencia_snss' => 'Pertenencia SNSS',
        'cen_type' => 'Tipo de establecimiento',
        'cen_scope' => 'Ámbito de funcionamiento',
        'cen_certification' => 'Certificación',
        'cen_adm_dependency' => 'Dependencia administrativa',
        'nivel_atencion' => 'Nivel de atención',
        'tiene_urgencia' => 'Tiene urgencia',
        'cen_emergency_type' => 'Tipo de urgencia',
        'tipo_sapu' => 'Tipo SAPU',
        'cen_provider_type' => 'Tipo sistema de salud',
        'nivel_complejidad' => 'Nivel de complejidad',
        'tipo_atencion' => 'Tipo de atención',
        'via' => 'Tipo de vía',
        'nombre_oficial' => 'Nombre oficial',
        'nombre_corto' => 'Nombre corto',
        'cod_antiguo' => 'Código antiguo',
        'codigo_madre' => 'Código madre',
        'cod_madre_antiguo' => 'Código madre antiguo',
        'cen_hie_dependency_id' => 'Servicio de salud',
        'address.cen_commune_id' => 'Comuna',
        'address.numero' => 'Número',
        'address.telefono' => 'Teléfono',
        'address.latitud' => 'Latitud',
        'address.longitud' => 'Longitud',
    ];

    public const API_FIELD_BY_KEY = [
        'estado_funcionamiento' => 'EstadoFuncionamiento',
        'pertenencia_snss' => 'TipoPertenenciaEstabGlosa',
        'cen_type' => 'TipoEstablecimientoGlosa',
        'cen_scope' => 'AmbitoFuncionamiento',
        'cen_certification' => 'Certificacion',
        'cen_adm_dependency' => 'DependenciaAdministrativa',
        'nivel_atencion' => 'NivelAtencionEstabglosa',
        'tiene_urgencia' => 'TieneServicioUrgencia',
        'cen_emergency_type' => 'TipoUrgencia',
        'tipo_sapu' => 'ClasificacionTipoSapu',
        'cen_provider_type' => 'TipoSistemaSaludGlosa',
        'nivel_complejidad' => 'NivelComplejidadEstabGlosa',
        'tipo_atencion' => 'TipoAtencionEstabGlosa',
        'via' => 'TipoViaGlosa',
    ];

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public static function resolve(string $fieldKey, string $enumClass, mixed $rawApiGlosa): CenCatalogGlosaResolution
    {
        $apiGlosaRaw = is_string($rawApiGlosa) ? CenApiGlosaNormalizer::trimGlosa($rawApiGlosa) : null;
        $syntactic = self::applySyntaxNormalizer($fieldKey, $apiGlosaRaw);

        if ($syntactic === null) {
            return new CenCatalogGlosaResolution(
                persistedValue: self::desconocidoValue($enumClass),
                apiGlosaRaw: $apiGlosaRaw,
                normalizedAfterSyntax: null,
                equivalenceApplied: false,
                isUnknown: true,
            );
        }

        $direct = $enumClass::tryFrom($syntactic);

        if ($direct !== null && $direct->name !== 'Desconocido') {
            return new CenCatalogGlosaResolution(
                persistedValue: $direct->value,
                apiGlosaRaw: $apiGlosaRaw,
                normalizedAfterSyntax: $syntactic,
                equivalenceApplied: false,
            );
        }

        $equivalence = self::equivalenceLookupFor($fieldKey);

        foreach (self::equivalenceLookupKeys($fieldKey, $apiGlosaRaw, $syntactic) as $lookupKey) {
            if (! isset($equivalence['targets'][$lookupKey])) {
                continue;
            }

            $target = $equivalence['targets'][$lookupKey];
            $enumCase = $enumClass::tryFrom($target);

            if ($enumCase === null) {
                continue;
            }

            $equivalenceApplied = $syntactic !== $target;

            return new CenCatalogGlosaResolution(
                persistedValue: $enumCase->value,
                apiGlosaRaw: $apiGlosaRaw,
                normalizedAfterSyntax: $syntactic,
                equivalenceApplied: $equivalenceApplied,
                rule: $equivalence['rules'][$lookupKey] ?? null,
            );
        }

        if ($direct !== null) {
            return new CenCatalogGlosaResolution(
                persistedValue: $direct->value,
                apiGlosaRaw: $apiGlosaRaw,
                normalizedAfterSyntax: $syntactic,
                equivalenceApplied: false,
                isUnknown: $direct->name === 'Desconocido',
            );
        }

        return new CenCatalogGlosaResolution(
            persistedValue: self::desconocidoValue($enumClass),
            apiGlosaRaw: $apiGlosaRaw,
            normalizedAfterSyntax: $syntactic,
            equivalenceApplied: false,
            isUnknown: true,
        );
    }

    /**
     * @return list<string>
     */
    public static function fieldKeys(): array
    {
        return array_keys(self::SYNTAX_NORMALIZERS);
    }

    /**
     * @return class-string<BackedEnum>
     */
    public static function enumClassForField(string $fieldKey): string
    {
        return match ($fieldKey) {
            'estado_funcionamiento' => CentroEstadoFuncionamiento::class,
            'pertenencia_snss' => CentroPertenenciaSnss::class,
            'cen_type' => CenCenterType::class,
            'cen_scope' => CenCenterScope::class,
            'cen_certification' => CenCenterCertification::class,
            'cen_adm_dependency' => CenCenterAdmDependency::class,
            'nivel_atencion' => CentroNivelAtencion::class,
            'tiene_urgencia' => CentroTieneUrgencia::class,
            'cen_emergency_type' => CenCenterEmergencyType::class,
            'tipo_sapu' => CentroTipoSapu::class,
            'cen_provider_type' => CenCenterProviderType::class,
            'nivel_complejidad' => CentroNivelComplejidad::class,
            'tipo_atencion' => CentroTipoAtencion::class,
            'via' => DireccionVia::class,
            default => throw new \InvalidArgumentException("Campo de catálogo desconocido: {$fieldKey}"),
        };
    }

    public static function normalizeLookupKey(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value) ?? $value), 'UTF-8');
    }

    /**
     * Clave de equivalencia al registrar en BD (misma lógica que en resolve).
     */
    public static function normalizedEquivalenceLookupKey(string $fieldKey, ?string $rawApiGlosa): string
    {
        $trimmed = is_string($rawApiGlosa) ? CenApiGlosaNormalizer::trimGlosa($rawApiGlosa) : null;

        if ($trimmed === null || $trimmed === '') {
            return '';
        }

        $syntactic = self::applySyntaxNormalizer($fieldKey, $trimmed);

        return self::normalizeLookupKey($syntactic ?? $trimmed);
    }

    /**
     * @return list<string>
     */
    private static function equivalenceLookupKeys(string $fieldKey, ?string $apiGlosaRaw, ?string $syntactic): array
    {
        $keys = [];

        if (is_string($syntactic) && $syntactic !== '') {
            $keys[] = self::normalizeLookupKey($syntactic);
        }

        $trimmedRaw = is_string($apiGlosaRaw) ? CenApiGlosaNormalizer::trimGlosa($apiGlosaRaw) : null;

        if ($trimmedRaw !== null && $trimmedRaw !== '') {
            $keys[] = self::normalizeLookupKey($trimmedRaw);
            $keys[] = self::normalizedEquivalenceLookupKey($fieldKey, $trimmedRaw);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function enumOptionsForField(string $fieldKey): array
    {
        $enumClass = self::enumClassForField($fieldKey);

        return array_map(
            fn (BackedEnum $case): array => ['value' => $case->value, 'label' => $case->value],
            array_filter(
                $enumClass::cases(),
                fn (BackedEnum $case): bool => $case->name !== 'Desconocido',
            ),
        );
    }

    private static function applySyntaxNormalizer(string $fieldKey, ?string $value): ?string
    {
        $callable = self::SYNTAX_NORMALIZERS[$fieldKey] ?? [CenApiGlosaNormalizer::class, 'collapseWhitespacePassthrough'];

        return ($callable[0])::{$callable[1]}($value);
    }

    /**
     * @return array{targets: array<string, string>, rules: array<string, string>}
     */
    private static function equivalenceLookupFor(string $fieldKey): array
    {
        $lookups = self::buildEquivalenceLookups();

        return $lookups[$fieldKey] ?? ['targets' => [], 'rules' => []];
    }

    /**
     * @return array<string, array{targets: array<string, string>, rules: array<string, string>}>
     */
    private static function buildEquivalenceLookups(): array
    {
        if (self::$equivalenceLookups !== null) {
            return self::$equivalenceLookups;
        }

        /** @var array<string, array{collapse?: array<string, list<string>>, alias?: array<string, string>}> $config */
        $config = config('cen_catalog_equivalences', []);
        $built = [];

        foreach ($config as $fieldKey => $rules) {
            if (! is_array($rules)) {
                continue;
            }

            $targets = [];
            $ruleMap = [];

            foreach ($rules['collapse'] ?? [] as $canonical => $variants) {
                if (! is_array($variants)) {
                    continue;
                }

                $allVariants = array_unique(array_merge([$canonical], $variants));

                foreach ($allVariants as $variant) {
                    if (! is_string($variant) || $variant === '') {
                        continue;
                    }

                    $key = self::normalizeLookupKey($variant);
                    $targets[$key] = $canonical;
                    $ruleMap[$key] = 'collapse:'.$canonical;
                }
            }

            foreach ($rules['alias'] ?? [] as $source => $target) {
                if (! is_string($source) || ! is_string($target)) {
                    continue;
                }

                $key = self::normalizeLookupKey($source);
                $targets[$key] = $target;
                $ruleMap[$key] = 'alias:'.$target;
            }

            $built[$fieldKey] = ['targets' => $targets, 'rules' => $ruleMap];
        }

        $databaseLookups = app(CenCatalogGlosaEquivalenceService::class)->activeDatabaseLookups();

        foreach ($databaseLookups as $fieldKey => $bucket) {
            if (! isset($built[$fieldKey])) {
                $built[$fieldKey] = ['targets' => [], 'rules' => []];
            }

            foreach ($bucket['targets'] as $key => $target) {
                $built[$fieldKey]['targets'][$key] = $target;
                $built[$fieldKey]['rules'][$key] = $bucket['rules'][$key] ?? 'alias:'.$target;
            }
        }

        self::$equivalenceLookups = $built;

        return self::$equivalenceLookups;
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public static function desconocidoValue(string $enumClass): string
    {
        foreach (['Desconocido', 'No Aplica', 'Pendiente', 'NO'] as $fallback) {
            $case = $enumClass::tryFrom($fallback);

            if ($case !== null) {
                return $case->value;
            }
        }

        throw new \RuntimeException("El enum {$enumClass} no tiene valor de respaldo (Desconocido / No Aplica / Pendiente).");
    }

    public static function resetEquivalenceCache(): void
    {
        self::$equivalenceLookups = null;
    }

    public static function resetCacheForTesting(): void
    {
        self::resetEquivalenceCache();
    }
}
