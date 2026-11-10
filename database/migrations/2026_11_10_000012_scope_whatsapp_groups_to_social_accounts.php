<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_groups', function (Blueprint $table): void {
            $table->dropUnique('whatsapp_groups_workspace_whatsapp_id_unique');
            $table->unique(
                ['workspace_id', 'social_account_id', 'whatsapp_id'],
                'whatsapp_groups_workspace_account_external_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_groups', function (Blueprint $table): void {
            $table->dropUnique('whatsapp_groups_workspace_account_external_unique');
            $table->unique('whatsapp_id', 'whatsapp_groups_whatsapp_id_unique');
        });
    }
};
