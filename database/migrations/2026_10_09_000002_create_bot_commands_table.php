<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_commands', function (Blueprint $table): void {
            $table->id();
            $table->string('bot_name', 100)->index();
            $table->string('type', 60)->index();
            $table->json('payload')->nullable();
            $table->string('idempotency_key', 180)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['bot_name', 'status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_commands');
    }
};
