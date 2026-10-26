<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('support_tickets', 'subcategory')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->dropColumn('subcategory');
            });
        }

        if (! Schema::hasColumn('support_tickets', 'attachment')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->json('attachment')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_tickets', 'attachment')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->dropColumn('attachment');
            });
        }

        if (! Schema::hasColumn('support_tickets', 'subcategory')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->string('subcategory', 64)->after('area');
            });
        }
    }
};
