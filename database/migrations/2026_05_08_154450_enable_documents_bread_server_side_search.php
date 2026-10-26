<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Habilita búsqueda y orden servidor en el BREAD `documents` (Voyager browse nativo).
     */
    public function up(): void
    {
        $row = DB::table('data_types')->where('name', 'documents')->first();
        if ($row === null) {
            return;
        }

        /** @var array<string, mixed> $details */
        $details = json_decode((string) ($row->details ?? '{}'), true);
        $details = is_array($details) ? $details : [];
        $defaultKey = isset($details['default_search_key']) ? trim((string) $details['default_search_key']) : '';
        if ($defaultKey === '') {
            $details['default_search_key'] = 'nombre';
        }

        $updates = ['server_side' => 1, 'details' => json_encode($details)];
        if (Schema::hasColumn('data_types', 'default_search_key')) {
            $colKey = trim((string) ($row->default_search_key ?? ''));
            $updates['default_search_key'] = $colKey === '' ? 'nombre' : $colKey;
        }

        DB::table('data_types')->where('name', 'documents')->update($updates);
    }

    public function down(): void
    {
        DB::table('data_types')->where('name', 'documents')->update(['server_side' => 0]);
    }
};
