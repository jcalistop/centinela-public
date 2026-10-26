<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-192: arreglar BREAD logo (resize height "null" rompe Intervention;
 * vistas edit/add/read/browse con URL pública del disco private).
 */
return new class extends Migration
{
    private const BREAD_SLUG = 'servicios-de-salud';

    private const FIELD = 'logo';

    public function up(): void
    {
        if (! Schema::hasTable('data_types') || ! Schema::hasTable('data_rows')) {
            return;
        }

        $dataTypeId = DB::table('data_types')->where('slug', self::BREAD_SLUG)->value('id');
        if (! $dataTypeId) {
            return;
        }

        $row = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', self::FIELD)
            ->first();

        if ($row === null) {
            return;
        }

        $details = [
            'quality' => '90%',
            'upsize' => true,
            'resize' => [
                'width' => '800',
            ],
            'view_browse' => 'voyager::servicios-de-salud.partials.browse-logo',
            'view_edit' => 'voyager::servicios-de-salud.partials.edit-logo',
            'view_add' => 'voyager::servicios-de-salud.partials.edit-logo',
        ];

        DB::table('data_rows')
            ->where('id', $row->id)
            ->update([
                'type' => 'image',
                'browse' => 1,
                'read' => 1,
                'edit' => 1,
                'add' => 1,
                'details' => json_encode($details, JSON_THROW_ON_ERROR),
            ]);

        // Normalizar backslashes Windows en paths ya guardados.
        $logos = DB::table('cen_hie_dependencies')
            ->whereNotNull('logo')
            ->where('logo', '!=', '')
            ->get(['id', 'logo']);

        foreach ($logos as $logoRow) {
            $normalized = str_replace('\\', '/', (string) $logoRow->logo);
            if ($normalized !== $logoRow->logo) {
                DB::table('cen_hie_dependencies')
                    ->where('id', $logoRow->id)
                    ->update(['logo' => $normalized]);
            }
        }
    }

    public function down(): void
    {
        // No revierte paths ni vistas; el logo sigue siendo usable.
    }
};
