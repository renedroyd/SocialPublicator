<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_catalog_campaign_publication_destination', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('publication_destination_id');
            $table->timestamps();

            $table->unique(
                ['campaign_id', 'publication_destination_id'],
                'campaign_publication_destination_unique',
            );
            $table->index('workspace_id', 'campaign_publication_destination_workspace_idx');

            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->cascadeOnDelete();
            $table->foreign(['workspace_id', 'campaign_id'], 'campaign_page_destination_campaign_fk')
                ->references(['workspace_id', 'id'])
                ->on('external_catalog_campaigns')
                ->cascadeOnDelete();
            $table->foreign(['workspace_id', 'publication_destination_id'], 'campaign_page_destination_destination_fk')
                ->references(['workspace_id', 'id'])
                ->on('publication_destinations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_catalog_campaign_publication_destination');
    }
};
