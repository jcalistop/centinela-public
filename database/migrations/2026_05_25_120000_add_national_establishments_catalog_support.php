<?php

use App\Support\Cen\CenApiGlosaNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cen_centers')) {
            return;
        }

        Schema::table('cen_centers', function (Blueprint $table) {
            if (! Schema::hasColumn('cen_centers', 'origen')) {
                $table->string('origen', 16)->default('api')->after('id');
            }
            if (! Schema::hasColumn('cen_centers', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('cen_centers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $this->backfillEnumGlosasToApi();

        if (! Schema::hasTable('cen_center_notes')) {
            Schema::create('cen_center_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cen_center_id')->constrained('cen_centers')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->text('body');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cen_center_sync_runs')) {
            Schema::create('cen_center_sync_runs', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 32);
                $table->string('ss_codigo', 32)->nullable();
                $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('soft_deleted_count')->default(0);
                $table->unsignedInteger('restored_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->unsignedInteger('warning_count')->default(0);
                $table->string('status', 32)->default('completed');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cen_center_sync_run_issues')) {
            Schema::create('cen_center_sync_run_issues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cen_center_sync_run_id')->constrained('cen_center_sync_runs')->cascadeOnDelete();
                $table->string('establecimiento_codigo', 32)->nullable();
                $table->string('severity', 16)->default('warning');
                $table->string('code', 64);
                $table->text('message');
                $table->json('context')->nullable();
                $table->timestamps();
            });
        }

        DB::table('cen_centers')
            ->whereNull('origen')
            ->update(['origen' => 'api']);
    }

    public function down(): void
    {
        Schema::dropIfExists('cen_center_sync_run_issues');
        Schema::dropIfExists('cen_center_sync_runs');
        Schema::dropIfExists('cen_center_notes');

        if (! Schema::hasTable('cen_centers')) {
            return;
        }

        Schema::table('cen_centers', function (Blueprint $table) {
            if (Schema::hasColumn('cen_centers', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('cen_centers', 'synced_at')) {
                $table->dropColumn('synced_at');
            }
            if (Schema::hasColumn('cen_centers', 'origen')) {
                $table->dropColumn('origen');
            }
        });
    }

    private function backfillEnumGlosasToApi(): void
    {
        foreach (CenApiGlosaNormalizer::legacyColumnMaps() as $column => $legacyMap) {
            foreach ($legacyMap as $legacy => $api) {
                DB::table('cen_centers')
                    ->where($column, $legacy)
                    ->update([$column => $api]);
            }
        }

        if (Schema::hasTable('cen_addresses') && Schema::hasColumn('cen_addresses', 'via')) {
            $viaLegacy = [
                'AVENIDA' => 'Avenida',
                'CALLE' => 'Calle',
                'CAMINO' => 'Camino',
                'CARRETERA' => 'Carretera',
                'OTRO' => 'Otro',
                'PASAJE' => 'Pasaje',
                'SECTOR' => 'Sector',
                'PENDIENTE' => 'Pendiente',
            ];

            foreach ($viaLegacy as $legacy => $api) {
                DB::table('cen_addresses')
                    ->where('via', $legacy)
                    ->update(['via' => $api]);
            }
        }
    }
};
