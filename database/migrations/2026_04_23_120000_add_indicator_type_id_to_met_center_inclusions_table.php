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
            Schema::table('met_center_inclusions', function (Blueprint $table) {
                $table->dropForeign(['cen_center_id']);
                $table->dropForeign(['year_id']);
                $table->dropForeign(['cen_hie_dependency_id']);
            });
        }

        Schema::table('met_center_inclusions', function (Blueprint $table) {
            $table->dropUnique('uq_met_center_inclusion');
        });

        Schema::table('met_center_inclusions', function (Blueprint $table) {
            $table->foreignId('indicator_type_id')
                ->nullable()
                ->after('year_id')
                ->constrained('indicator_types')
                ->restrictOnDelete();
        });

        $defaultTypeId = DB::table('indicator_types')
            ->where('route', 'metas-18834')
            ->value('id');

        if ($defaultTypeId !== null) {
            DB::table('met_center_inclusions')
                ->whereNull('indicator_type_id')
                ->update(['indicator_type_id' => $defaultTypeId]);
        }

        if (! $isSqlite) {
            Schema::table('met_center_inclusions', function (Blueprint $table) {
                $table->foreignId('indicator_type_id')->nullable(false)->change();
            });
        }

        Schema::table('met_center_inclusions', function (Blueprint $table) {
            $table->unique(
                ['cen_center_id', 'year_id', 'indicator_type_id'],
                'uq_met_center_inclusion_per_instrument'
            );
            $table->index(['year_id', 'cen_hie_dependency_id', 'indicator_type_id'], 'met_ctr_incl_scope_idx');
        });

        if (! $isSqlite) {
            Schema::table('met_center_inclusions', function (Blueprint $table) {
                $table->foreign('cen_center_id')->references('id')->on('cen_centers');
                $table->foreign('year_id')->references('id')->on('years');
                $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        Schema::disableForeignKeyConstraints();

        if (! $isSqlite) {
            Schema::table('met_center_inclusions', function (Blueprint $table) {
                $table->dropForeign(['cen_center_id']);
                $table->dropForeign(['year_id']);
                $table->dropForeign(['cen_hie_dependency_id']);
            });
        }

        Schema::table('met_center_inclusions', function (Blueprint $table) {
            $table->dropIndex('met_ctr_incl_scope_idx');
            $table->dropUnique('uq_met_center_inclusion_per_instrument');
        });

        Schema::table('met_center_inclusions', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['indicator_type_id']);
            }
            $table->dropColumn('indicator_type_id');
        });

        Schema::table('met_center_inclusions', function (Blueprint $table) {
            $table->unique(['cen_center_id', 'year_id'], 'uq_met_center_inclusion');
        });

        if (! $isSqlite) {
            Schema::table('met_center_inclusions', function (Blueprint $table) {
                $table->foreign('cen_center_id')->references('id')->on('cen_centers');
                $table->foreign('year_id')->references('id')->on('years');
                $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies');
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
