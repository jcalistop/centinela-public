<?php

namespace App\Support\Voyager;

use App\Http\Controllers\StorageController;
use App\Models\Document;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\Permission;

/**
 * Resuelve el slug BREAD de {@see Document} configurado en Voyager (`documentos` vs `documents`).
 */
final class DocumentBread
{
    public static function slug(): string
    {
        $dataType = Voyager::model('DataType')->where('model_name', Document::class)->first();

        return $dataType !== null && is_string($dataType->slug) && $dataType->slug !== ''
            ? $dataType->slug
            : 'documentos';
    }

    /**
     * Sufijo de claves Voyager en `permissions` (`browse_{name}`, etc.; ver {@see Permission::generateFor}).
     */
    public static function permissionTableName(): string
    {
        $dataType = Voyager::model('DataType')->where('model_name', Document::class)->first();

        return $dataType !== null && is_string($dataType->name) && $dataType->name !== ''
            ? $dataType->name
            : 'documents';
    }

    /**
     * Slug usado en las rutas públicas de archivo (`/download`, `/view`) — ambos soportados por {@see StorageController}.
     */
    public static function fileRouteSlug(): string
    {
        return self::slug();
    }
}
