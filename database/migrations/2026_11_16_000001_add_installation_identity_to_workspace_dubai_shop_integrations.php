<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_dubai_shop_integrations', function (Blueprint $table): void {
            $table->uuid('installation_id')->nullable()->unique()->after('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_dubai_shop_integrations', function (Blueprint $table): void {
            $table->dropUnique(['installation_id']);
            $table->dropColumn('installation_id');
        });
    }
};
