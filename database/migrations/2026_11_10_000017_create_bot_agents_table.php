<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_agents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('instance_id', 191);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->json('social_account_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'instance_id'], 'bot_agents_workspace_instance_unique');
            $table->index(['workspace_id', 'is_active'], 'bot_agents_workspace_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_agents');
    }
};
