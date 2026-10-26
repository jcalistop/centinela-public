<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SLUG = 'establecimientos';

    private const CONTROLLER = 'App\\Http\\Controllers\\Voyager\\CenCenterController';

    public function up(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        DB::table('data_types')
            ->where('slug', self::SLUG)
            ->update([
                'controller' => self::CONTROLLER,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        DB::table('data_types')
            ->where('slug', self::SLUG)
            ->where('controller', self::CONTROLLER)
            ->update([
                'controller' => null,
                'updated_at' => now(),
            ]);
    }
};
