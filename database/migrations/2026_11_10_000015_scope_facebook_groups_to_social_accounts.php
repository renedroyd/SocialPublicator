<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_groups', function (Blueprint $table): void {
            $table->dropUnique('facebook_groups_workspace_facebook_id_unique');
            $table->unique(
                ['workspace_id', 'social_account_id', 'facebook_id'],
                'facebook_groups_workspace_account_external_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('facebook_groups', function (Blueprint $table): void {
            $table->dropUnique('facebook_groups_workspace_account_external_unique');
            $table->unique(['workspace_id', 'facebook_id'], 'facebook_groups_workspace_facebook_id_unique');
        });
    }
};
