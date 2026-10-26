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
            ->where('slug', 'indicadores-comges')
            ->value('id');

        if (! $dataTypeId) {
            return;
        }

        $row = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'com_commitment_id')
            ->first();

        if (! $row) {
            return;
        }

        $details = json_decode($row->details ?? '{}', true) ?: [];
        $details['view_edit'] = 'voyager::indicadores-comges.formfields.commitment-select-edit';

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
            ->where('slug', 'indicadores-comges')
            ->value('id');

        if (! $dataTypeId) {
            return;
        }

        $row = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'com_commitment_id')
            ->first();

        if (! $row) {
            return;
        }

        $details = json_decode($row->details ?? '{}', true) ?: [];
        unset($details['view_edit']);

        DB::table('data_rows')
            ->where('id', $row->id)
            ->update(['details' => json_encode($details)]);
    }
};
