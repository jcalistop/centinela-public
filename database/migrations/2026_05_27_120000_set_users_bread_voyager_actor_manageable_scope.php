<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataType;

return new class extends Migration
{
    /**
     * SDD-085: control-gestión solo resuelve usuarios de su SS sin rol admin/parametrizador.
     */
    public function up(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        $dataType = DataType::query()->where('slug', 'users')->first();
        if (! $dataType instanceof DataType) {
            return;
        }

        $dataType->scope = 'voyagerActorManageable';
        $dataType->save();
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        $dataType = DataType::query()->where('slug', 'users')->first();
        if (! $dataType instanceof DataType) {
            return;
        }

        $dataType->scope = null;
        $dataType->save();
    }
};
