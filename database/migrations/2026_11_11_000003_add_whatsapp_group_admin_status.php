<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_groups', function (Blueprint $table): void {
            $table->boolean('is_admin')->nullable()->after('active');
            $table->timestamp('admin_checked_at')->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_groups', function (Blueprint $table): void {
            $table->dropColumn(['is_admin', 'admin_checked_at']);
        });
    }
};
