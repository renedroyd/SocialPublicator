<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40)->default('dubai_shop');
            $table->uuid('external_id');
            $table->uuid('product_external_id')->nullable();
            $table->string('item_type', 20);
            $table->string('sku')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category_name')->nullable();
            $table->string('category_slug')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('brand_slug')->nullable();
            $table->text('image_url')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('visible')->default(true);
            $table->unsignedBigInteger('price_amount_minor');
            $table->char('currency', 3);
            $table->json('availability')->nullable();
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->uuid('sync_run_id')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['source', 'active', 'visible']);
            $table->index(['source', 'product_external_id']);
            $table->index(['source', 'item_type', 'currency']);
        });

        Schema::create('external_catalog_warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40)->default('dubai_shop');
            $table->uuid('external_id');
            $table->string('code', 40)->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->boolean('online_fulfillment_enabled')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->uuid('sync_run_id')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['source', 'active', 'online_fulfillment_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_catalog_warehouses');
        Schema::dropIfExists('external_catalog_items');
    }
};
