<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('platform', 30)->index();
            $table->string('status', 30)->default('offline')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('version', 100)->nullable();
            $table->string('host', 255)->nullable();
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_statuses');
    }
};
