<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_catalog_campaigns', function (Blueprint $table): void {
            $table->string('rotation_strategy', 24)->default('least_recent');
            $table->unsignedSmallInteger('group_size')->default(4);
            $table->json('posting_weekdays')->nullable();
            $table->time('posting_window_start')->default('09:00:00');
            $table->time('posting_window_end')->default('21:00:00');
            $table->string('timezone', 64)->default('UTC');
            $table->timestamp('last_selected_at')->nullable();
        });

        Schema::table('external_catalog_campaign_item', function (Blueprint $table): void {
            $table->timestamp('last_selected_at')->nullable();
            $table->unsignedInteger('selection_count')->default(0);
        });

        Schema::create('external_catalog_campaign_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('external_catalog_campaigns')->cascadeOnDelete();
            $table->foreignId('external_catalog_item_id')->constrained('external_catalog_items')->cascadeOnDelete();
            $table->uuid('selection_group_id');
            $table->unsignedInteger('available_quantity')->default(0);
            $table->json('warehouse_external_ids')->nullable();
            $table->timestamp('selected_at');
            $table->string('status', 24)->default('planned');
            $table->timestamps();

            $table->unique(['campaign_id', 'external_catalog_item_id', 'selection_group_id'], 'campaign_selection_item_group_unique');
            $table->index(['campaign_id', 'selected_at']);
            $table->index(['selection_group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_catalog_campaign_selections');

        Schema::table('external_catalog_campaign_item', function (Blueprint $table): void {
            $table->dropColumn(['last_selected_at', 'selection_count']);
        });

        Schema::table('external_catalog_campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'rotation_strategy',
                'group_size',
                'posting_weekdays',
                'posting_window_start',
                'posting_window_end',
                'timezone',
                'last_selected_at',
            ]);
        });
    }
};
