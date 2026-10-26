<?php

use App\Support\Cen\CenApiGlosaNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cen_centers')) {
            return;
        }

        foreach (CenApiGlosaNormalizer::legacyColumnMaps() as $column => $legacyMap) {
            if (! Schema::hasColumn('cen_centers', $column)) {
                continue;
            }

            foreach ($legacyMap as $legacy => $api) {
                DB::table('cen_centers')
                    ->where($column, $legacy)
                    ->update([$column => $api]);
            }
        }
    }

    public function down(): void
    {
        // No reversible.
    }
};
