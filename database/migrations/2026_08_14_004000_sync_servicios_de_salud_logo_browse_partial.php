<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-192: asegurar partial browse del logo en BREAD servicios-de-salud
 * (si la migración de columna ya corrió sin view_browse).
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

        $details = [];
        if (is_string($row->details) && $row->details !== '') {
            $decoded = json_decode($row->details, true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        $details['view_browse'] = 'voyager::servicios-de-salud.partials.browse-logo';

        DB::table('data_rows')
            ->where('id', $row->id)
            ->update([
                'browse' => 1,
                'read' => 1,
                'edit' => 1,
                'add' => 1,
                'details' => json_encode($details, JSON_THROW_ON_ERROR),
            ]);
    }

    public function down(): void
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

        $details = [];
        if (is_string($row->details) && $row->details !== '') {
            $decoded = json_decode($row->details, true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        unset($details['view_browse']);

        DB::table('data_rows')
            ->where('id', $row->id)
            ->update([
                'details' => json_encode($details, JSON_THROW_ON_ERROR),
            ]);
    }
};
