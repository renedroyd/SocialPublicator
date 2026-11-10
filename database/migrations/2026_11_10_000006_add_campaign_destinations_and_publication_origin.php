<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_catalog_campaigns', function (Blueprint $table): void {
            $table->string('destination', 16)->default('whatsapp');
        });

        Schema::create('external_catalog_campaign_whatsapp_group', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('external_catalog_campaigns')->cascadeOnDelete();
            $table->foreignId('whatsapp_group_id')->constrained('whatsapp_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['campaign_id', 'whatsapp_group_id'], 'campaign_whatsapp_group_unique');
        });

        Schema::create('external_catalog_campaign_facebook_group', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('external_catalog_campaigns')->cascadeOnDelete();
            $table->foreignId('facebook_group_id')->constrained('facebook_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['campaign_id', 'facebook_group_id'], 'campaign_facebook_group_unique');
        });

        Schema::table('publications', function (Blueprint $table): void {
            $table->foreignId('external_campaign_id')
                ->nullable()
                ->constrained('external_catalog_campaigns')
                ->nullOnDelete();
            $table->uuid('external_selection_group_id')->nullable()->unique();
            $table->foreignId('external_campaign_trigger_id')
                ->nullable()
                ->constrained('external_catalog_campaign_triggers')
                ->nullOnDelete()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table): void {
            $table->dropForeign(['external_campaign_id']);
            $table->dropForeign(['external_campaign_trigger_id']);
            $table->dropUnique(['external_selection_group_id']);
            $table->dropUnique(['external_campaign_trigger_id']);
            $table->dropColumn([
                'external_campaign_id',
                'external_selection_group_id',
                'external_campaign_trigger_id',
            ]);
        });

        Schema::dropIfExists('external_catalog_campaign_facebook_group');
        Schema::dropIfExists('external_catalog_campaign_whatsapp_group');

        Schema::table('external_catalog_campaigns', function (Blueprint $table): void {
            $table->dropColumn('destination');
        });
    }
};
