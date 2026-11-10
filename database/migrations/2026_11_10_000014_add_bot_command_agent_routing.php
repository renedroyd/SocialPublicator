<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_commands', function (Blueprint $table): void {
            $table->string('agent_instance_id', 191)->nullable()->after('bot_name');
            $table->index(
                ['workspace_id', 'bot_name', 'agent_instance_id', 'status', 'available_at'],
                'bot_commands_agent_claim_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('bot_commands', function (Blueprint $table): void {
            $table->dropIndex('bot_commands_agent_claim_index');
            $table->dropColumn('agent_instance_id');
        });
    }
};
