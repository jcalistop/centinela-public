<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;
use TCG\Voyager\Http\Controllers\VoyagerUserController;

return new class extends Migration
{
    /**
     * Voyager enruta el BREAD users con el controlador declarado en {@see data_types.controller}.
     * Sin esto se usa {@see VoyagerBaseController}, que no ejecuta
     * {@see VoyagerUserController::update} ni un hook Centinela.
     */
    public function up(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        DB::table('data_types')->where('slug', 'users')->update([
            'controller' => '\\App\\Http\\Controllers\\Voyager\\CentinelaVoyagerUserController',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        DB::table('data_types')->where('slug', 'users')->update([
            'controller' => null,
            'updated_at' => now(),
        ]);
    }
};
