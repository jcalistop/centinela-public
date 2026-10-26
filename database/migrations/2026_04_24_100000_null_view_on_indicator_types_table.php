<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El formulario Centinela ya no persiste `view`; `Route::view` se desactiva forzando NULL.
     */
    public function up(): void
    {
        if (! Schema::hasTable('indicator_types')) {
            return;
        }

        DB::table('indicator_types')->update(['view' => null]);
    }

    public function down(): void
    {
        // irreversible (no se conoce el valor previo)
    }
};
