<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-282 — catálogo de paneles de información nacional (guest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_info_panels', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('html_path')->nullable();
            $table->string('csv_path')->nullable();
            $table->string('preview_png_path')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('publicado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_info_panels');
    }
};
