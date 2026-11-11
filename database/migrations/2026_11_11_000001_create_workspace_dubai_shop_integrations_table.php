<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_dubai_shop_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('api_base_url', 2048)->nullable();
            $table->text('api_token')->nullable();
            $table->string('webhook_key_id', 120)->nullable()->unique();
            $table->text('webhook_secret')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('campaign_dispatch_enabled')->default(false);
            $table->unsignedSmallInteger('timeout')->default(15);
            $table->unsignedInteger('webhook_tolerance_seconds')->default(300);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'campaign_dispatch_enabled'], 'workspace_dubai_shop_enabled_dispatch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_dubai_shop_integrations');
    }
};
