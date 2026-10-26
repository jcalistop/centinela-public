<?php

namespace App\Support\Cen;

use App\Models\CenCenterSyncRunIssue;

/**
 * Textos orientados al usuario final del módulo Establecimientos (admin / control de gestión).
 */
final class CenEstablishmentsUiCopy
{
    public const HELP_CATALOGO_NACIONAL = 'Datos oficiales del Ministerio de Salud publicados en datos.gob.cl. Centinela los descarga y actualiza periódicamente.';

    public const HELP_SYNC_SS = 'Actualiza solo los establecimientos de su Servicio de Salud. El proceso corre en segundo plano; puede tardar unos minutos.';

    public const HELP_SYNC_NACIONAL = 'Actualiza todos los Servicios de Salud del país. Solo administradores. Puede tardar varios minutos (misma tarea programada semanal del sistema).';

    public const HELP_SYNC_HISTORY = 'Cada fila es una ejecución de sincronización con el catálogo nacional. Los números indican cuántos establecimientos se crearon, actualizaron, dieron de baja u omitieron.';

    public const HELP_COLUMN_CREADOS = 'Establecimientos nuevos incorporados a Centinela en esa ejecución.';

    public const HELP_COLUMN_ACTUALIZADOS = 'Registros que ya existían y recibieron datos actualizados del catálogo.';

    public const HELP_COLUMN_BAJA = 'Establecimientos ocultados en Centinela: cerrados en el catálogo o que dejaron de aparecer en la descarga filtrada.';

    public const HELP_COLUMN_OMITIDOS = 'Filas del catálogo que no se aplicaron (por ejemplo, registro manual o datos incompletos).';

    public const HELP_COLUMN_INCIDENCIAS = 'Advertencias e informaciones registradas durante la ejecución (textos no reconocidos, comunas faltantes, etc.). El total es histórico de esa ejecución.';

    public const HELP_COLUMN_PENDIENTES = 'Textos del catálogo aún sin regla de equivalencia y que el administrador puede vincular a un valor de Centinela.';

    public const HELP_EQUIVALENCIAS = 'Cuando el catálogo nacional usa un texto distinto al de Centinela, aquí se define cómo traducirlo en la próxima sincronización.';

    public const HELP_GLOSA_API = 'Texto tal como viene en datos.gob.cl (puede variar en mayúsculas o redacción).';

    public const HELP_AUDITORIA = 'Historial de cambios hechos en Centinela (por usuarios o por sincronización), distinto del detalle de cada ejecución de catálogo.';

    /** @var array<string, string> */
    private const ISSUE_CODE_LABELS = [
        'unmapped_enum' => 'Texto de catálogo sin equivalencia',
        'missing_enum_glosa' => 'Campo vacío en catálogo',
        'enum_desconocido' => 'Valor no clasificado',
        'glosa_equiv_applied' => 'Equivalencia aplicada',
        'unknown_comuna' => 'Comuna no encontrada',
        'manual_origen' => 'Registro manual (no se modifica)',
        'missing_codigo' => 'Sin código de establecimiento',
        'missing_ss_codigo' => 'Sin Servicio de Salud en catálogo',
        'unknown_ss_codigo' => 'Servicio de Salud no registrado',
        'skipped' => 'Omitido',
        'closed_establishment_soft_deleted' => 'Cierre en catálogo — baja en Centinela',
        'sync_failed' => 'Error en sincronización',
    ];

    /** @var array<string, string> */
    private const ISSUE_SEVERITY_LABELS = [
        'error' => 'Error',
        'warning' => 'Advertencia',
        'info' => 'Información',
        'skipped' => 'Omitido',
    ];

    /** @var array<string, string> */
    private const CHANGE_DETAIL_LABELS = [
        'missing_codigo' => 'Sin código en el catálogo nacional',
        'missing_ss_codigo' => 'Sin código de Servicio de Salud en el catálogo',
        'unknown_ss_codigo' => 'Servicio de Salud no existe en Centinela',
        'manual_origen' => 'Alta manual: no se actualiza con el catálogo',
        'missing_from_catalog' => 'Ya no aparece en la descarga del catálogo',
        'closed_establishment' => 'Estado de cierre en el catálogo nacional',
        'skipped' => 'Omitido en esta ejecución',
        'sin_cambios_datos' => 'Sin cambios de datos (solo fecha de sincronización)',
    ];

    /** @var array<string, string> */
    private const RULE_KIND_LABELS = [
        'alias' => 'Sinónimo / variante de texto',
        'collapse' => 'Unificar varias redacciones',
    ];

    public static function issueCodeLabel(string $code): string
    {
        return self::ISSUE_CODE_LABELS[$code] ?? self::humanizeToken($code);
    }

    public static function issueSeverityLabel(string $severity): string
    {
        return self::ISSUE_SEVERITY_LABELS[$severity] ?? self::humanizeToken($severity);
    }

    public static function changeDetailLabel(string $detail): string
    {
        return self::CHANGE_DETAIL_LABELS[$detail] ?? self::humanizeToken($detail);
    }

    public static function ruleKindLabel(string $value): string
    {
        return self::RULE_KIND_LABELS[$value] ?? self::humanizeToken($value);
    }

    public static function syncRunLabel(int $runId): string
    {
        return 'Ejecución #'.$runId;
    }

    /**
     * @param  array<string, string>  $fieldLabels
     */
    public static function issueMessageForDisplay(CenCenterSyncRunIssue $issue, array $fieldLabels = []): string
    {
        $context = is_array($issue->context) ? $issue->context : [];
        $fieldKey = is_string($context['field'] ?? null) ? $context['field'] : '';
        $fieldLabel = $fieldLabels[$fieldKey] ?? ($fieldKey !== '' ? self::humanizeToken($fieldKey) : 'campo');
        $apiGlosa = is_string($context['api_glosa'] ?? null) ? $context['api_glosa'] : null;
        $normalized = is_string($context['normalized_api'] ?? null) ? $context['normalized_api'] : null;
        $persisted = is_string($context['persisted'] ?? null) ? $context['persisted'] : null;

        return match ($issue->code) {
            'unmapped_enum' => $apiGlosa !== null
                ? "En «{$fieldLabel}» el catálogo trae «{$apiGlosa}», que aún no tiene equivalencia en Centinela."
                : 'Texto del catálogo nacional sin equivalencia configurada.',
            'missing_enum_glosa' => "En «{$fieldLabel}» el catálogo no envió texto; se guardó como Desconocido.",
            'enum_desconocido' => $apiGlosa !== null
                ? "En «{$fieldLabel}» el valor «{$apiGlosa}» no pudo clasificarse y quedó como Desconocido."
                : "En «{$fieldLabel}» se usó el valor Desconocido.",
            'glosa_equiv_applied' => $normalized !== null && $persisted !== null
                ? "En «{$fieldLabel}» se aplicó equivalencia: «{$normalized}» → «{$persisted}»."
                : "Se aplicó una regla de equivalencia en «{$fieldLabel}».",
            'unknown_comuna' => is_string($context['message'] ?? null) && ! self::looksTechnical((string) $context['message'])
                ? (string) $context['message']
                : 'La comuna indicada en el catálogo no está en el maestro de comunas de Centinela.',
            'manual_origen' => 'Es un registro de alta manual; la sincronización nacional no lo modifica.',
            'missing_codigo' => 'El registro del catálogo no trae código de establecimiento.',
            'missing_ss_codigo' => 'El registro no indica Servicio de Salud.',
            'unknown_ss_codigo' => 'El Servicio de Salud del catálogo no está registrado en Centinela.',
            'closed_establishment_soft_deleted' => 'Estado de cierre en el catálogo: se ocultó el establecimiento en Centinela (origen catálogo nacional).',
            'sync_failed' => is_string($context['message'] ?? null) && $context['message'] !== ''
                ? 'La sincronización falló: '.$context['message']
                : 'La sincronización no pudo completarse.',
            default => self::looksTechnical($issue->message)
                ? (self::ISSUE_CODE_LABELS[$issue->code] ?? $issue->message)
                : $issue->message,
        };
    }

    public static function looksTechnical(string $text): bool
    {
        if (str_contains($text, '_') && preg_match('/[a-z]+_[a-z]+/', $text)) {
            return true;
        }

        $needles = [
            'unmapped_enum',
            'cen_center_sync',
            'CKAN',
            'config/',
            'auditing',
            'soft delete',
            'persistid',
        ];

        foreach ($needles as $needle) {
            if (stripos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function humanizeToken(string $token): string
    {
        $token = str_replace(['.', '_'], ' ', $token);

        return ucfirst(trim($token));
    }
}
