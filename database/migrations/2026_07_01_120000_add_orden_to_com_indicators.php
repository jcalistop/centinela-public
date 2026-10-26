<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('com_indicators', function (Blueprint $table): void {
            $table->unsignedInteger('orden')->default(0)->after('com_commitment_id');
            $table->index(['com_commitment_id', 'orden']);
        });

        $commitmentIds = DB::table('com_commitments')->orderBy('id')->pluck('id');

        foreach ($commitmentIds as $commitmentId) {
            $indicatorIds = DB::table('com_indicators')
                ->where('com_commitment_id', $commitmentId)
                ->orderBy('clave')
                ->orderBy('id')
                ->pluck('id');

            foreach ($indicatorIds as $index => $indicatorId) {
                DB::table('com_indicators')
                    ->where('id', $indicatorId)
                    ->update(['orden' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('com_indicators', function (Blueprint $table): void {
            $table->dropIndex(['com_commitment_id', 'orden']);
            $table->dropColumn('orden');
        });
    }
};
