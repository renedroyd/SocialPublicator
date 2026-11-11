<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_account_connections', function (Blueprint $table): void {
            $table->string('agent_instance_id', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('social_account_connections', function (Blueprint $table): void {
            $table->string('agent_instance_id', 120)->nullable()->change();
        });
    }
};
