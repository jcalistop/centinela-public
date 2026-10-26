<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_info_panels', function (Blueprint $table) {
            $table->string('tipo', 32)->default('html_bundle')->after('slug');
            $table->string('embed_url', 2048)->nullable()->after('preview_png_path');
            $table->string('autor')->nullable()->after('descripcion');
            $table->string('autor_email')->nullable()->after('autor');
            $table->string('institucion')->nullable()->after('autor_email');
            $table->string('version_panel', 64)->nullable()->after('institucion');
        });
    }

    public function down(): void
    {
        Schema::table('public_info_panels', function (Blueprint $table) {
            $table->dropColumn([
                'tipo',
                'embed_url',
                'autor',
                'autor_email',
                'institucion',
                'version_panel',
            ]);
        });
    }
};
