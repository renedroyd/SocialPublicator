<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_dubai_shop_integrations', function (Blueprint $table): void {
            $table->string('previous_webhook_key_id', 120)->nullable()->unique()->after('webhook_secret');
            $table->text('previous_webhook_secret')->nullable()->after('previous_webhook_key_id');
            $table->timestamp('previous_webhook_secret_expires_at')->nullable()->after('previous_webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_dubai_shop_integrations', function (Blueprint $table): void {
            $table->dropUnique(['previous_webhook_key_id']);
            $table->dropColumn([
                'previous_webhook_key_id',
                'previous_webhook_secret',
                'previous_webhook_secret_expires_at',
            ]);
        });
    }
};
