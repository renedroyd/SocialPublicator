<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_catalog_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('selection_mode', 24)->default('manual');
            $table->string('publication_mode', 24)->default('grouped');
            $table->unsignedSmallInteger('max_publications_per_day')->default(3);
            $table->unsignedInteger('minimum_interval_minutes')->default(240);
            $table->string('out_of_stock_behavior', 24)->default('pause');
            $table->string('restock_behavior', 24)->default('publish');
            $table->string('price_change_behavior', 24)->default('immediate');
            $table->timestamps();

            $table->index(['is_active', 'selection_mode']);
        });

        Schema::create('external_catalog_campaign_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('external_catalog_campaigns')->cascadeOnDelete();
            $table->foreignId('external_catalog_item_id')->constrained('external_catalog_items')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'external_catalog_item_id'], 'campaign_external_item_unique');
        });

        Schema::create('external_catalog_campaign_warehouse', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('external_catalog_campaigns')->cascadeOnDelete();
            $table->foreignId('external_catalog_warehouse_id')->constrained('external_catalog_warehouses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['campaign_id', 'external_catalog_warehouse_id'], 'campaign_external_warehouse_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_catalog_campaign_warehouse');
        Schema::dropIfExists('external_catalog_campaign_item');
        Schema::dropIfExists('external_catalog_campaigns');
    }
};
