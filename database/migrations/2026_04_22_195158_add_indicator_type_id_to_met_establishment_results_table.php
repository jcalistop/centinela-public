<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::disableForeignKeyConstraints();

        if (! $isSqlite) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->dropForeign(['cen_center_id']);
                $table->dropForeign(['cen_hie_dependency_id']);
                $table->dropForeign(['year_id']);
            });
        }

        Schema::table('met_establishment_results', function (Blueprint $table) {
            $table->dropUnique('uq_met_estab_result');
        });

        Schema::table('met_establishment_results', function (Blueprint $table) {
            $table->foreignId('indicator_type_id')
                ->nullable()
                ->after('year_id')
                ->constrained('indicator_types');
        });

        $defaultTypeId = DB::table('indicator_types')
            ->where('route', 'metas-18834')
            ->value('id');

        if ($defaultTypeId !== null) {
            DB::table('met_establishment_results')
                ->whereNull('indicator_type_id')
                ->update(['indicator_type_id' => $defaultTypeId]);
        }

        if (! $isSqlite) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->foreignId('indicator_type_id')->nullable(false)->change();
            });
        }

        Schema::table('met_establishment_results', function (Blueprint $table) {
            $table->unique(
                ['cen_center_id', 'year_id', 'periodo', 'indicator_type_id'],
                'uq_met_estab_result_per_instrument'
            );
        });

        if (! $isSqlite) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->foreign('cen_center_id')->references('id')->on('cen_centers');
                $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies');
                $table->foreign('year_id')->references('id')->on('years');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::disableForeignKeyConstraints();

        if (! $isSqlite) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->dropForeign(['cen_center_id']);
                $table->dropForeign(['cen_hie_dependency_id']);
                $table->dropForeign(['year_id']);
            });
        }

        Schema::table('met_establishment_results', function (Blueprint $table) {
            $table->dropUnique('uq_met_estab_result_per_instrument');
        });

        Schema::table('met_establishment_results', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['indicator_type_id']);
            }
            $table->dropColumn('indicator_type_id');
        });

        Schema::table('met_establishment_results', function (Blueprint $table) {
            $table->unique(['cen_center_id', 'year_id', 'periodo'], 'uq_met_estab_result');
        });

        if (! $isSqlite) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->foreign('cen_center_id')->references('id')->on('cen_centers');
                $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies');
                $table->foreign('year_id')->references('id')->on('years');
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
