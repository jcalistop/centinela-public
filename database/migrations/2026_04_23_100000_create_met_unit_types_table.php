<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 32)->unique();
            $table->string('nombre', 120);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['codigo' => 'URG', 'nombre' => 'Urgencia', 'orden' => 10],
            ['codigo' => 'UCI', 'nombre' => 'Unidad de cuidados intensivos', 'orden' => 20],
            ['codigo' => 'PAB', 'nombre' => 'Pabellón', 'orden' => 30],
        ];

        foreach ($rows as $row) {
            DB::table('met_unit_types')->insert([
                'codigo' => $row['codigo'],
                'nombre' => $row['nombre'],
                'orden' => $row['orden'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('met_unit_types');
    }
};
