<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_types') || ! Schema::hasTable('data_rows')) {
            return;
        }

        $dataTypeId = DB::table('data_types')
            ->where('slug', 'instrumentos-evaluacion')
            ->value('id');

        if (! $dataTypeId) {
            return;
        }

        $row = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'icon_class')
            ->first();

        if (! $row) {
            return;
        }

        $details = json_decode($row->details ?? '{}', true) ?: [];
        $details['view_browse'] = 'voyager::instrumentos-evaluacion.partials.browse-icon-class';

        DB::table('data_rows')
            ->where('id', $row->id)
            ->update(['details' => json_encode($details)]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_types') || ! Schema::hasTable('data_rows')) {
            return;
        }

        $dataTypeId = DB::table('data_types')
            ->where('slug', 'instrumentos-evaluacion')
            ->value('id');

        if (! $dataTypeId) {
            return;
        }

        $row = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'icon_class')
            ->first();

        if (! $row || $row->details === null) {
            return;
        }

        $details = json_decode($row->details, true) ?: [];
        unset($details['view_browse']);

        DB::table('data_rows')
            ->where('id', $row->id)
            ->update(['details' => $details === [] ? null : json_encode($details)]);
    }
};
