<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_catalog_campaign_selections', function (Blueprint $table): void {
            $table->text('last_error')->nullable();
        });

        Schema::table('external_catalog_campaign_triggers', function (Blueprint $table): void {
            $table->text('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('external_catalog_campaign_triggers', function (Blueprint $table): void {
            $table->dropColumn('last_error');
        });

        Schema::table('external_catalog_campaign_selections', function (Blueprint $table): void {
            $table->dropColumn('last_error');
        });
    }
};
