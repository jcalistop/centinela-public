<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($rows) > 0;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if ((string) ($row->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function dropUniqueIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropUnique($indexName);
        });
    }

    public function up(): void
    {
        if (! Schema::hasColumn('met_monthly_data', 'met_measurement_unit_id')) {
            Schema::table('met_monthly_data', function (Blueprint $table) {
                $table->foreignId('met_measurement_unit_id')
                    ->nullable()
                    ->after('year_id')
                    ->constrained('met_measurement_units')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('met_monthly_data', 'measurement_unit_scope_key')) {
            Schema::table('met_monthly_data', function (Blueprint $table) {
                $table->unsignedBigInteger('measurement_unit_scope_key')->default(0)->after('met_measurement_unit_id');
            });
        }

        DB::table('met_monthly_data')->update(['measurement_unit_scope_key' => 0]);

        if (! $this->indexExists('met_monthly_data', 'uq_met_monthly_per_unit_scope')) {
            Schema::table('met_monthly_data', function (Blueprint $table) {
                $table->unique(
                    ['met_data_field_id', 'cen_center_id', 'year_id', 'mes', 'measurement_unit_scope_key'],
                    'uq_met_monthly_per_unit_scope'
                );
            });
        }

        // Importante (MySQL): no dropear uq_met_monthly antes de tener un índice alternativo
        // que cubra la FK (met_data_field_id). El unique nuevo lo cubre como prefijo.
        $this->dropUniqueIfExists('met_monthly_data', 'uq_met_monthly');

        if (! Schema::hasColumn('met_indicator_configurations', 'met_measurement_unit_id')) {
            Schema::table('met_indicator_configurations', function (Blueprint $table) {
                $table->foreignId('met_measurement_unit_id')
                    ->nullable()
                    ->after('cen_center_id')
                    ->constrained('met_measurement_units')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('met_indicator_configurations', 'configuration_unit_scope_key')) {
            Schema::table('met_indicator_configurations', function (Blueprint $table) {
                $table->unsignedBigInteger('configuration_unit_scope_key')->default(0)->after('met_measurement_unit_id');
            });
        }

        DB::table('met_indicator_configurations')->update(['configuration_unit_scope_key' => 0]);

        if (! $this->indexExists('met_indicator_configurations', 'uq_met_ind_config_per_unit_scope')) {
            Schema::table('met_indicator_configurations', function (Blueprint $table) {
                $table->unique(
                    ['met_indicator_id', 'year_id', 'cen_center_id', 'configuration_unit_scope_key'],
                    'uq_met_ind_config_per_unit_scope'
                );
            });
        }

        // MySQL: mantener índice alternativo antes de eliminar uq_met_ind_config.
        $this->dropUniqueIfExists('met_indicator_configurations', 'uq_met_ind_config');

        if (! Schema::hasColumn('met_results', 'met_measurement_unit_id')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->foreignId('met_measurement_unit_id')
                    ->nullable()
                    ->after('cen_center_id')
                    ->constrained('met_measurement_units')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('met_results', 'result_unit_scope_key')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->unsignedBigInteger('result_unit_scope_key')->default(0)->after('met_measurement_unit_id');
            });
        }

        DB::table('met_results')->update(['result_unit_scope_key' => 0]);

        if (! $this->indexExists('met_results', 'uq_met_result_per_unit_scope')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->unique(
                    ['met_indicator_id', 'cen_center_id', 'year_id', 'periodo', 'result_unit_scope_key'],
                    'uq_met_result_per_unit_scope'
                );
            });
        }

        // En SQLite (tests) sí es necesario eliminar el unique antiguo, porque impide
        // múltiples resultados por unidad. En MySQL puede estar siendo usado por FK.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->dropUniqueIfExists('met_results', 'uq_met_result');
        }
    }

    public function down(): void
    {
        // met_results: índice alternativo antes de dropear el unique (FK met_indicator_id usa el prefijo).
        if ($this->indexExists('met_results', 'uq_met_result_per_unit_scope')) {
            if (! $this->indexExists('met_results', 'idx_met_results_rollback_indicator_id')) {
                Schema::table('met_results', function (Blueprint $table) {
                    $table->index('met_indicator_id', 'idx_met_results_rollback_indicator_id');
                });
            }
            $this->dropUniqueIfExists('met_results', 'uq_met_result_per_unit_scope');
        }

        Schema::table('met_results', function (Blueprint $table) {
            if (Schema::hasColumn('met_results', 'met_measurement_unit_id')) {
                $table->dropConstrainedForeignId('met_measurement_unit_id');
            }
            if (Schema::hasColumn('met_results', 'result_unit_scope_key')) {
                $table->dropColumn('result_unit_scope_key');
            }
        });

        if (! $this->indexExists('met_results', 'uq_met_result')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->unique(['met_indicator_id', 'cen_center_id', 'year_id', 'periodo'], 'uq_met_result');
            });
        }

        if ($this->indexExists('met_results', 'idx_met_results_rollback_indicator_id')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->dropIndex('idx_met_results_rollback_indicator_id');
            });
        }

        // met_indicator_configurations: mismo patrón que 2026_04_19_212822 down().
        if ($this->indexExists('met_indicator_configurations', 'uq_met_ind_config_per_unit_scope')) {
            if (! $this->indexExists('met_indicator_configurations', 'idx_met_ind_config_rollback_indicator_id')) {
                Schema::table('met_indicator_configurations', function (Blueprint $table) {
                    $table->index('met_indicator_id', 'idx_met_ind_config_rollback_indicator_id');
                });
            }
            $this->dropUniqueIfExists('met_indicator_configurations', 'uq_met_ind_config_per_unit_scope');
        }

        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            if (Schema::hasColumn('met_indicator_configurations', 'met_measurement_unit_id')) {
                $table->dropConstrainedForeignId('met_measurement_unit_id');
            }
            if (Schema::hasColumn('met_indicator_configurations', 'configuration_unit_scope_key')) {
                $table->dropColumn('configuration_unit_scope_key');
            }
        });

        if (! $this->indexExists('met_indicator_configurations', 'uq_met_ind_config')) {
            Schema::table('met_indicator_configurations', function (Blueprint $table) {
                $table->unique(['met_indicator_id', 'year_id', 'cen_center_id'], 'uq_met_ind_config');
            });
        }

        if ($this->indexExists('met_indicator_configurations', 'idx_met_ind_config_rollback_indicator_id')) {
            Schema::table('met_indicator_configurations', function (Blueprint $table) {
                $table->dropIndex('idx_met_ind_config_rollback_indicator_id');
            });
        }

        // met_monthly_data: el unique per-unit empieza por met_data_field_id (FK met_data_field_id).
        if ($this->indexExists('met_monthly_data', 'uq_met_monthly_per_unit_scope')) {
            if (! $this->indexExists('met_monthly_data', 'idx_met_monthly_rollback_data_field_id')) {
                Schema::table('met_monthly_data', function (Blueprint $table) {
                    $table->index('met_data_field_id', 'idx_met_monthly_rollback_data_field_id');
                });
            }
            $this->dropUniqueIfExists('met_monthly_data', 'uq_met_monthly_per_unit_scope');
        }

        Schema::table('met_monthly_data', function (Blueprint $table) {
            if (Schema::hasColumn('met_monthly_data', 'met_measurement_unit_id')) {
                $table->dropConstrainedForeignId('met_measurement_unit_id');
            }
            if (Schema::hasColumn('met_monthly_data', 'measurement_unit_scope_key')) {
                $table->dropColumn('measurement_unit_scope_key');
            }
        });

        if (! $this->indexExists('met_monthly_data', 'uq_met_monthly')) {
            Schema::table('met_monthly_data', function (Blueprint $table) {
                $table->unique(['met_data_field_id', 'cen_center_id', 'year_id', 'mes'], 'uq_met_monthly');
            });
        }

        if ($this->indexExists('met_monthly_data', 'idx_met_monthly_rollback_data_field_id')) {
            Schema::table('met_monthly_data', function (Blueprint $table) {
                $table->dropIndex('idx_met_monthly_rollback_data_field_id');
            });
        }
    }
};
