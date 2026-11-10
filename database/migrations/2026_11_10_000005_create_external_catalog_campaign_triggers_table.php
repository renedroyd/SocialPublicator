<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_catalog_campaign_triggers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('external_catalog_campaigns')->cascadeOnDelete();
            $table->foreignId('external_catalog_item_id')->constrained('external_catalog_items')->cascadeOnDelete();
            $table->uuid('source_event_id');
            $table->string('trigger_type', 80);
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->unique(['source_event_id', 'campaign_id', 'trigger_type'], 'campaign_trigger_source_unique');
            $table->index(['campaign_id', 'status', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_catalog_campaign_triggers');
    }
};
