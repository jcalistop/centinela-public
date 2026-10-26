<?php

namespace App\Support\Cen;

use App\Models\CenCenter;
use BackedEnum;
use Carbon\CarbonInterface;

/**
 * Columnas de exportación XLSX alineadas al recurso CKAN nacional
 * (datastore resource 2c44d782-3365-44e3-aefb-2c8b8363a1bc).
 *
 * @see CenCenterNationalCatalogMapper
 */
final class CenCenterExportApiColumnMap
{
    /**
     * Orden y nombres oficiales de la API (sin el campo interno CKAN `_id`).
     *
     * @var list<string>
     */
    private const API_FIELD_ORDER = [
        'EstablecimientoCodigoAntiguo',
        'EstablecimientoCodigo',
        'EstablecimientoCodigoMadreAntiguo',
        'EstablecimientoCodigoMadreNuevo',
        'RegionCodigo',
        'RegionGlosa',
        'SeremiSaludCodigo_ServicioDeSaludCodigo',
        'SeremiSaludGlosa_ServicioDeSaludGlosa',
        'TipoPertenenciaEstabGlosa',
        'TipoEstablecimientoGlosa',
        'AmbitoFuncionamiento',
        'EstablecimientoGlosa',
        'Certificacion',
        'DependenciaAdministrativa',
        'NivelAtencionEstabglosa',
        'ComunaCodigo',
        'ComunaGlosa',
        'TipoViaGlosa',
        'Numero',
        'NombreVia',
        'TelefonoMovil_TelefonoFijo',
        'FechaInicioFuncionamientoEstab',
        'TieneServicioUrgencia',
        'TipoUrgencia',
        'ClasificacionTipoSapu',
        'Latitud',
        'Longitud',
        'TipoSistemaSaludGlosa',
        'EstadoFuncionamiento',
        'NivelComplejidadEstabGlosa',
        'TipoAtencionEstabGlosa',
        'FechaIncorporacion',
        'FechaCierre',
    ];

    /**
     * Campos Centinela no presentes en el catálogo CKAN (sufijo al final del XLSX).
     *
     * @var list<string>
     */
    private const CENTINELA_FIELD_ORDER = [
        'Centinela_Id',
        'Centinela_NombreCorto',
        'Centinela_Origen',
        'Centinela_SyncedAt',
        'Centinela_DeletedAt',
        'Centinela_CreatedAt',
        'Centinela_UpdatedAt',
        'Centinela_AddressId',
        'Centinela_AddressCreatedAt',
        'Centinela_AddressUpdatedAt',
    ];

    /**
     * @return list<string>
     */
    public static function headerOrder(): array
    {
        return array_merge(self::API_FIELD_ORDER, self::CENTINELA_FIELD_ORDER);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildRow(CenCenter $center): array
    {
        $address = $center->cenAddress;
        $commune = $address?->cenCommune;
        $region = $commune?->cenProvince?->cenRegion;
        $dependency = $center->cenHieDependency;

        $apiValues = [
            'EstablecimientoCodigoAntiguo' => $center->cod_antiguo,
            'EstablecimientoCodigo' => $center->codigo,
            'EstablecimientoCodigoMadreAntiguo' => $center->cod_madre_antiguo,
            'EstablecimientoCodigoMadreNuevo' => $center->codigo_madre,
            'RegionCodigo' => $region?->codigo,
            'RegionGlosa' => $region?->nombre,
            'SeremiSaludCodigo_ServicioDeSaludCodigo' => $dependency?->codigo,
            'SeremiSaludGlosa_ServicioDeSaludGlosa' => $dependency?->nombre,
            'TipoPertenenciaEstabGlosa' => self::serializeValue($center->pertenencia_snss),
            'TipoEstablecimientoGlosa' => self::serializeValue($center->cen_type),
            'AmbitoFuncionamiento' => self::serializeValue($center->cen_scope),
            'EstablecimientoGlosa' => $center->nombre_oficial,
            'Certificacion' => self::serializeValue($center->cen_certification),
            'DependenciaAdministrativa' => self::serializeValue($center->cen_adm_dependency),
            'NivelAtencionEstabglosa' => self::serializeValue($center->nivel_atencion),
            'ComunaCodigo' => $commune?->codigo,
            'ComunaGlosa' => $commune?->nombre,
            'TipoViaGlosa' => self::serializeValue($address?->via),
            'Numero' => $address?->numero,
            'NombreVia' => $address?->direccion,
            'TelefonoMovil_TelefonoFijo' => $address?->telefono,
            'FechaInicioFuncionamientoEstab' => null,
            'TieneServicioUrgencia' => self::serializeValue($center->tiene_urgencia),
            'TipoUrgencia' => self::serializeValue($center->cen_emergency_type),
            'ClasificacionTipoSapu' => self::serializeValue($center->tipo_sapu),
            'Latitud' => $address?->latitud,
            'Longitud' => $address?->longitud,
            'TipoSistemaSaludGlosa' => self::serializeValue($center->cen_provider_type),
            'EstadoFuncionamiento' => self::serializeValue($center->estado_funcionamiento),
            'NivelComplejidadEstabGlosa' => self::serializeValue($center->nivel_complejidad),
            'TipoAtencionEstabGlosa' => self::serializeValue($center->tipo_atencion),
            'FechaIncorporacion' => null,
            'FechaCierre' => null,
        ];

        $centinelaValues = [
            'Centinela_Id' => $center->getKey(),
            'Centinela_NombreCorto' => $center->nombre_corto,
            'Centinela_Origen' => self::serializeValue($center->origen),
            'Centinela_SyncedAt' => self::serializeValue($center->synced_at),
            'Centinela_DeletedAt' => self::serializeValue($center->deleted_at),
            'Centinela_CreatedAt' => self::serializeValue($center->created_at),
            'Centinela_UpdatedAt' => self::serializeValue($center->updated_at),
            'Centinela_AddressId' => $address?->getKey(),
            'Centinela_AddressCreatedAt' => self::serializeValue($address?->created_at),
            'Centinela_AddressUpdatedAt' => self::serializeValue($address?->updated_at),
        ];

        $row = [];

        foreach (self::headerOrder() as $header) {
            $row[$header] = $apiValues[$header] ?? $centinelaValues[$header] ?? null;
        }

        return $row;
    }

    private static function serializeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }
}
