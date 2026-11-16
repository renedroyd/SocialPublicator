<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_event_inbox', function (Blueprint $table): void {
            $table->timestamp('dispatch_reserved_at')->nullable()->index();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->uuid('processing_token')->nullable()->unique();
            $table->timestamp('failed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('integration_event_inbox', function (Blueprint $table): void {
            $table->dropUnique(['processing_token']);
            $table->dropIndex(['dispatch_reserved_at']);
            $table->dropIndex(['lease_expires_at']);
            $table->dropColumn([
                'dispatch_reserved_at',
                'lease_expires_at',
                'processing_token',
                'failed_at',
            ]);
        });
    }
};
