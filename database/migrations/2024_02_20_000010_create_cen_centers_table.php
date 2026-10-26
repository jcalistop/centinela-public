<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCenCentersTable extends Migration
{
    public function up()
    {
        Schema::create('cen_centers', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('cod_antiguo')->nullable();
            $table->string('codigo_madre')->nullable();
            $table->string('cod_madre_antiguo')->nullable();
            $table->bigInteger('cen_hie_dependency_id')->unsigned();
            $table->enum('pertenencia_snss', ['NO PERTENECIENTE', 'PERTENECIENTE', 'NO APLICA']);
            $table->bigInteger('cen_type_id')->unsigned();
            $table->bigInteger('cen_scope_id')->unsigned();
            $table->string('nombre_oficial');
            $table->string('nombre_corto');
            $table->bigInteger('cen_certification_id')->unsigned();
            $table->bigInteger('cen_adm_dependency_id')->unsigned();
            $table->enum('nivel_atencion', ['PRIMARIO', 'SECUNDARIO', 'TERCIARIO', 'PENDIENTE', 'NO APLICA']);
            $table->enum('tiene_urgencia', ['SI', 'NO', 'NO APLICA', 'PENDIENTE']);
            $table->bigInteger('cen_emergency_type_id')->unsigned();
            $table->enum('tipo_sapu', ['CORTO', 'VERANO', 'LARGO', 'AVANZADO', 'PENDIENTE', 'NO APLICA', '']);
            $table->bigInteger('cen_provider_type_id')->unsigned();
            $table->enum('estado_funcionamiento', ['OPERACION HABITUAL', 'OPERACION TRANSITORIA', 'CIERRE TEMPORAL', 'CIERRE DEFINITIVO']);
            $table->enum('nivel_complejidad', ['ALTA', 'MEDIANA', 'BAJA', 'NO APLICA', 'PENDIENTE']);
            $table->enum('tipo_atencion', ['ABIERTA', 'CERRADA', 'ABIERTA Y CERRADA', 'NO APLICA', 'PENDIENTE']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cen_centers');
    }
}
