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
            Schema::table('met_center_adosados', function (Blueprint $table) {
                $table->dropForeign(['cen_center_madre_id']);
                $table->dropForeign(['cen_center_adosado_id']);
                $table->dropForeign(['year_id']);
                $table->dropForeign(['cen_hie_dependency_id']);
            });
        }

        Schema::table('met_center_adosados', function (Blueprint $table) {
            $table->dropUnique('uq_met_center_adosado');
        });

        Schema::table('met_center_adosados', function (Blueprint $table) {
            $table->foreignId('indicator_type_id')
                ->nullable()
                ->after('year_id')
                ->constrained('indicator_types')
                ->restrictOnDelete();
        });

        $type18834Id = DB::table('indicator_types')
            ->where('route', 'metas-18834')
            ->value('id');

        if ($type18834Id !== null) {
            DB::table('met_center_adosados')
                ->whereNull('indicator_type_id')
                ->update(['indicator_type_id' => $type18834Id]);
        }

        $type19664Id = DB::table('indicator_types')
            ->where('route', 'metas-19664')
            ->value('id');

        if ($type18834Id !== null && $type19664Id !== null) {
            $alreadyDuplicated = DB::table('met_center_adosados')
                ->where('indicator_type_id', $type19664Id)
                ->exists();

            if (! $alreadyDuplicated) {
                $rows = DB::table('met_center_adosados')
                    ->where('indicator_type_id', $type18834Id)
                    ->get([
                        'cen_center_madre_id',
                        'cen_center_adosado_id',
                        'year_id',
                        'cen_hie_dependency_id',
                        'created_at',
                    ]);

                $now = now();

                foreach ($rows as $row) {
                    DB::table('met_center_adosados')->insert([
                        'cen_center_madre_id' => $row->cen_center_madre_id,
                        'cen_center_adosado_id' => $row->cen_center_adosado_id,
                        'year_id' => $row->year_id,
                        'cen_hie_dependency_id' => $row->cen_hie_dependency_id,
                        'indicator_type_id' => $type19664Id,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (! $isSqlite) {
            Schema::table('met_center_adosados', function (Blueprint $table) {
                $table->foreignId('indicator_type_id')->nullable(false)->change();
            });
        }

        Schema::table('met_center_adosados', function (Blueprint $table) {
            $table->unique(
                ['cen_center_adosado_id', 'year_id', 'cen_hie_dependency_id', 'indicator_type_id'],
                'uq_met_center_adosado_per_instrument'
            );
            $table->index(
                ['cen_center_madre_id', 'year_id', 'indicator_type_id'],
                'met_ctr_ados_scope_idx'
            );
        });

        if (! $isSqlite) {
            Schema::table('met_center_adosados', function (Blueprint $table) {
                $table->foreign('cen_center_madre_id')->references('id')->on('cen_centers');
                $table->foreign('cen_center_adosado_id')->references('id')->on('cen_centers');
                $table->foreign('year_id')->references('id')->on('years');
                $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        $type19664Id = DB::table('indicator_types')
            ->where('route', 'metas-19664')
            ->value('id');

        if ($type19664Id !== null) {
            DB::table('met_center_adosados')
                ->where('indicator_type_id', $type19664Id)
                ->delete();
        }

        Schema::disableForeignKeyConstraints();

        if (! $isSqlite) {
            Schema::table('met_center_adosados', function (Blueprint $table) {
                $table->dropForeign(['cen_center_madre_id']);
                $table->dropForeign(['cen_center_adosado_id']);
                $table->dropForeign(['year_id']);
                $table->dropForeign(['cen_hie_dependency_id']);
            });
        }

        Schema::table('met_center_adosados', function (Blueprint $table) {
            $table->dropIndex('met_ctr_ados_scope_idx');
            $table->dropUnique('uq_met_center_adosado_per_instrument');
        });

        Schema::table('met_center_adosados', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['indicator_type_id']);
            }
            $table->dropColumn('indicator_type_id');
        });

        Schema::table('met_center_adosados', function (Blueprint $table) {
            $table->unique(
                ['cen_center_adosado_id', 'year_id', 'cen_hie_dependency_id'],
                'uq_met_center_adosado'
            );
        });

        if (! $isSqlite) {
            Schema::table('met_center_adosados', function (Blueprint $table) {
                $table->foreign('cen_center_madre_id')->references('id')->on('cen_centers');
                $table->foreign('cen_center_adosado_id')->references('id')->on('cen_centers');
                $table->foreign('year_id')->references('id')->on('years');
                $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies');
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
