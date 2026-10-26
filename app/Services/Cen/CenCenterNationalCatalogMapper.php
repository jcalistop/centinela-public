<?php

namespace App\Services\Cen;

use App\Enums\Cen\CenCenterAdmDependency;
use App\Enums\Cen\CenCenterCertification;
use App\Enums\Cen\CenCenterEmergencyType;
use App\Enums\Cen\CenCenterOrigen;
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
use App\Models\CenCommune;
use App\Services\Cen\Dto\MappedNationalEstablishment;
use App\Support\Cen\CenApiGlosaNormalizer;
use App\Support\Cen\CenCatalogGlosaResolver;
use App\Support\Cen\CenServicioSaludCodigo;
use App\Support\Cen\Dto\CenCatalogGlosaResolution;

final class CenCenterNationalCatalogMapper
{
    public function __construct(
        private readonly CenCenterNombreCortoBuilder $nombreCortoBuilder = new CenCenterNombreCortoBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     */
    public function map(array $record): MappedNationalEstablishment
    {
        $warnings = [];
        $codigo = CenApiGlosaNormalizer::trimGlosa($record['EstablecimientoCodigo'] ?? null);

        if ($codigo === null) {
            return new MappedNationalEstablishment('', [], null, true, 'missing_codigo');
        }

        $ssCodigo = CenApiGlosaNormalizer::trimGlosa($record['SeremiSaludCodigo_ServicioDeSaludCodigo'] ?? null);

        if ($ssCodigo === null) {
            return new MappedNationalEstablishment($codigo, [], null, true, 'missing_ss_codigo');
        }

        $dependency = CenServicioSaludCodigo::findDependency($ssCodigo);

        if ($dependency === null) {
            return new MappedNationalEstablishment($codigo, [], null, true, 'unknown_ss_codigo');
        }

        $glosa = CenApiGlosaNormalizer::trimGlosa($record['EstablecimientoGlosa'] ?? null) ?? $codigo;

        $center = [
            'codigo' => $codigo,
            'cod_antiguo' => CenApiGlosaNormalizer::trimGlosa($record['EstablecimientoCodigoAntiguo'] ?? null),
            'codigo_madre' => CenApiGlosaNormalizer::trimGlosa($record['EstablecimientoCodigoMadreNuevo'] ?? null),
            'cod_madre_antiguo' => CenApiGlosaNormalizer::trimGlosa($record['EstablecimientoCodigoMadreAntiguo'] ?? null),
            'cen_hie_dependency_id' => $dependency->id,
            'nombre_oficial' => $glosa,
            'nombre_corto' => $this->nombreCortoBuilder->fromEstablecimientoGlosa($glosa),
            'origen' => CenCenterOrigen::Api->value,
            'pertenencia_snss' => $this->resolveCatalogEnum('pertenencia_snss', CentroPertenenciaSnss::class, $record['TipoPertenenciaEstabGlosa'] ?? null, $warnings),
            'cen_type' => $this->resolveCatalogEnum('cen_type', CenCenterType::class, $record['TipoEstablecimientoGlosa'] ?? null, $warnings),
            'cen_scope' => $this->resolveCatalogEnum('cen_scope', CenCenterScope::class, $record['AmbitoFuncionamiento'] ?? null, $warnings),
            'cen_certification' => $this->resolveCatalogEnum('cen_certification', CenCenterCertification::class, $record['Certificacion'] ?? null, $warnings),
            'cen_adm_dependency' => $this->resolveCatalogEnum('cen_adm_dependency', CenCenterAdmDependency::class, $record['DependenciaAdministrativa'] ?? null, $warnings),
            'nivel_atencion' => $this->resolveCatalogEnum('nivel_atencion', CentroNivelAtencion::class, $record['NivelAtencionEstabglosa'] ?? null, $warnings),
            'tiene_urgencia' => $this->resolveCatalogEnum('tiene_urgencia', CentroTieneUrgencia::class, $record['TieneServicioUrgencia'] ?? null, $warnings),
            'cen_emergency_type' => $this->resolveCatalogEnum('cen_emergency_type', CenCenterEmergencyType::class, $record['TipoUrgencia'] ?? null, $warnings),
            'tipo_sapu' => $this->resolveCatalogEnum('tipo_sapu', CentroTipoSapu::class, $record['ClasificacionTipoSapu'] ?? null, $warnings),
            'cen_provider_type' => $this->resolveCatalogEnum('cen_provider_type', CenCenterProviderType::class, $record['TipoSistemaSaludGlosa'] ?? null, $warnings),
            'estado_funcionamiento' => $this->resolveCatalogEnum('estado_funcionamiento', CentroEstadoFuncionamiento::class, $record['EstadoFuncionamiento'] ?? null, $warnings),
            'nivel_complejidad' => $this->resolveCatalogEnum('nivel_complejidad', CentroNivelComplejidad::class, $record['NivelComplejidadEstabGlosa'] ?? null, $warnings),
            'tipo_atencion' => $this->resolveCatalogEnum('tipo_atencion', CentroTipoAtencion::class, $record['TipoAtencionEstabGlosa'] ?? null, $warnings),
            'synced_at' => now(),
        ];

        $address = $this->mapAddress($record, $warnings);

        return new MappedNationalEstablishment($codigo, $center, $address, false, null, $warnings);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<array{code: string, message: string, severity: string, context?: array<string, mixed>}>  $warnings
     * @return array<string, mixed>|null
     */
    private function mapAddress(array $record, array &$warnings): ?array
    {
        $comunaCodigo = CenApiGlosaNormalizer::trimGlosa($record['ComunaCodigo'] ?? null);

        if ($comunaCodigo === null) {
            return null;
        }

        $commune = CenCommune::query()->where('codigo', $comunaCodigo)->first();

        if ($commune === null) {
            $warnings[] = [
                'code' => 'unknown_comuna',
                'message' => "La comuna código {$comunaCodigo} del catálogo no está en el maestro de comunas de Centinela.",
                'severity' => 'warning',
            ];

            return null;
        }

        return [
            'cen_commune_id' => $commune->id,
            'via' => $this->resolveCatalogEnum('via', DireccionVia::class, $record['TipoViaGlosa'] ?? null, $warnings),
            'numero' => CenApiGlosaNormalizer::trimGlosa($record['Numero'] ?? null),
            'telefono' => CenApiGlosaNormalizer::trimGlosa($record['TelefonoMovil_TelefonoFijo'] ?? null),
            'latitud' => $this->decimalOrNull($record['Latitud'] ?? null),
            'longitud' => $this->decimalOrNull($record['Longitud'] ?? null),
        ];
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     * @param  list<array{code: string, message: string, severity: string, context?: array<string, mixed>}>  $warnings
     */
    private function resolveCatalogEnum(string $fieldKey, string $enumClass, mixed $rawApiGlosa, array &$warnings): string
    {
        $resolution = CenCatalogGlosaResolver::resolve($fieldKey, $enumClass, $rawApiGlosa);
        $this->appendResolutionWarnings($fieldKey, $enumClass, $resolution, $warnings);

        return $resolution->persistedValue;
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     * @param  list<array{code: string, message: string, severity: string, context?: array<string, mixed>}>  $warnings
     */
    private function appendResolutionWarnings(string $fieldKey, string $enumClass, CenCatalogGlosaResolution $resolution, array &$warnings): void
    {
        $fieldLabel = CenCatalogGlosaResolver::FIELD_LABELS[$fieldKey] ?? $fieldKey;

        if ($resolution->equivalenceApplied) {
            $warnings[] = [
                'code' => 'glosa_equiv_applied',
                'message' => "En «{$fieldLabel}» se aplicó equivalencia: «{$resolution->normalizedAfterSyntax}» → «{$resolution->persistedValue}».",
                'severity' => 'info',
                'context' => [
                    'field' => $fieldKey,
                    'api_glosa' => $resolution->apiGlosaRaw,
                    'normalized_api' => $resolution->normalizedAfterSyntax,
                    'persisted' => $resolution->persistedValue,
                    'rule' => $resolution->rule,
                ],
            ];
        }

        if ($resolution->isUnknown) {
            if ($resolution->apiGlosaRaw === null) {
                $warnings[] = [
                    'code' => 'missing_enum_glosa',
                    'message' => "En «{$fieldLabel}» el catálogo no envió texto; se guardó como Desconocido.",
                    'severity' => 'warning',
                ];

                return;
            }

            $warnings[] = [
                'code' => 'unmapped_enum',
                'message' => "En «{$fieldLabel}» el catálogo trae «{$resolution->apiGlosaRaw}», sin equivalencia en Centinela.",
                'severity' => 'warning',
                'context' => [
                    'field' => $fieldKey,
                    'api_glosa' => $resolution->apiGlosaRaw,
                    'normalized_api' => $resolution->normalizedAfterSyntax,
                ],
            ];

            return;
        }

        if (
            ! $resolution->isUnknown
            && ! $resolution->equivalenceApplied
            && $resolution->persistedValue === CenCatalogGlosaResolver::desconocidoValue($enumClass)
            && $resolution->apiGlosaRaw !== null
        ) {
            $warnings[] = [
                'code' => 'enum_desconocido',
                'message' => "En «{$fieldLabel}» el valor «{$resolution->apiGlosaRaw}» quedó como Desconocido.",
                'severity' => 'warning',
            ];
        }
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }
}
