<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('github_release_snapshots')) {
            return;
        }

        Schema::create('github_release_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('tag_name', 32)->unique();
            $table->string('name')->nullable();
            $table->longText('body');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('github_release_id')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_release_snapshots');
    }
};
