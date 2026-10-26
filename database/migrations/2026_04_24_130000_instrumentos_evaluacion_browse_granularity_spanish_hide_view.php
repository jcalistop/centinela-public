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

        $granularity = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'measurement_granularity')
            ->first();

        if ($granularity) {
            $details = json_decode($granularity->details ?? '{}', true) ?: [];
            $details['view_browse'] = 'voyager::instrumentos-evaluacion.partials.browse-measurement-granularity';

            DB::table('data_rows')
                ->where('id', $granularity->id)
                ->update([
                    'display_name' => 'Granularidad de medición',
                    'details' => json_encode($details),
                ]);
        }

        DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'view')
            ->update(['browse' => 0]);
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

        $granularity = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'measurement_granularity')
            ->first();

        if ($granularity && $granularity->details !== null) {
            $details = json_decode($granularity->details, true) ?: [];
            unset($details['view_browse']);

            DB::table('data_rows')
                ->where('id', $granularity->id)
                ->update([
                    'display_name' => 'Measurement granularity',
                    'details' => $details === [] ? null : json_encode($details),
                ]);
        }

        DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'view')
            ->update(['browse' => 1]);
    }
};
