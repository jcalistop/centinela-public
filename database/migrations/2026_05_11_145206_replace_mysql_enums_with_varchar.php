<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->convertMetDataFieldsEnumsToVarchar();
            $this->convertCenCentersAndAddressesEnumsToVarchar();

            return;
        }

        if ($driver !== 'mysql') {
            return;
        }

        Schema::table('adp_sensitivity_table_rows', function (Blueprint $table) {
            $table->string('operador_minimo', 2)->default('>=')->change();
            $table->string('operador_maximo', 2)->default('<')->change();
        });

        Schema::table('adp_indicator_targets', function (Blueprint $table) {
            $table->string('target_operador', 2)->nullable()->change();
        });

        Schema::table('adp_indicator_catalog_items', function (Blueprint $table) {
            $table->string('meta_operador', 2)->nullable()->change();
            $table->string('periodicidad', 20)->default('ANUAL')->change();
            $table->string('decimales_modo', 20)->nullable()->change();
        });

        Schema::table('adp_indicators', function (Blueprint $table) {
            $table->string('meta_operador', 2)->nullable()->change();
            $table->string('periodicidad', 20)->default('ANUAL')->change();
            $table->string('decimales_modo', 20)->nullable()->change();
        });

        Schema::table('com_verifiable_documents', function (Blueprint $table) {
            $table->string('verifiable_type', 20)->default('EVALUACION')->change();
        });

        Schema::table('com_evaluations', function (Blueprint $table) {
            $table->string('tipo_evaluacion', 20)->change();
        });

        Schema::table('com_actions', function (Blueprint $table) {
            $table->string('tipo_medio', 20)->nullable()->change();
        });

        Schema::table('cen_hie_dependencies', function (Blueprint $table) {
            $table->string('tipo_dependencia', 20)->change();
        });

        Schema::table('cutoffs', function (Blueprint $table) {
            $table->string('clave', 20)->change();
            $table->string('tipo_corte', 20)->change();
        });

        Schema::table('indicator_type_settings', function (Blueprint $table) {
            $table->string('etapa_vigente', 20)->default('CERRADO')->change();
            $table->string('tipo_evaluacion', 20)->default('NO DEFINIDO')->change();
        });

        $this->convertMetDataFieldsEnumsToVarchar();

        Schema::table('met_indicators', function (Blueprint $table) {
            $table->string('meta_operador', 2)->nullable()->change();
            $table->string('decimales_modo', 20)->default('redondear')->change();
        });

        $this->convertCenCentersAndAddressesEnumsToVarchar();
    }

    /**
     * También en SQLite: el CHECK del enum original sobrevive a la reconstrucción de
     * tabla y rechaza los tipos de periodicidad añadidos después (trimestral, anual).
     */
    private function convertMetDataFieldsEnumsToVarchar(): void
    {
        if (! Schema::hasTable('met_data_fields')) {
            return;
        }

        Schema::table('met_data_fields', function (Blueprint $table) {
            $table->string('componente')->change();
            $table->string('input_type', 20)->default('mensual')->change();
            $table->string('aggregation_type', 20)->nullable()->default('sumatoria')->change();
        });
    }

    private function convertCenCentersAndAddressesEnumsToVarchar(): void
    {
        if (Schema::hasTable('cen_addresses')) {
            Schema::table('cen_addresses', function (Blueprint $table) {
                $table->string('via', 20)->change();
            });
        }

        if (! Schema::hasTable('cen_centers')) {
            return;
        }

        Schema::table('cen_centers', function (Blueprint $table) {
            $table->string('pertenencia_snss', 30)->change();
            $table->string('nivel_atencion', 20)->change();
            $table->string('tiene_urgencia', 20)->change();
            $table->string('tipo_sapu', 20)->change();
            $table->string('estado_funcionamiento', 40)->change();
            $table->string('nivel_complejidad', 20)->change();
            $table->string('tipo_atencion', 30)->change();
        });
    }

    public function down(): void
    {
        // No reversible automáticamente: restaurar ENUM nativo exige DDL manual.
    }
};
