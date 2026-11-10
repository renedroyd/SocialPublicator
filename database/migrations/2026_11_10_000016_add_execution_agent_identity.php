<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publication_executions', function (Blueprint $table): void {
            $table->string('agent_instance_id', 191)->nullable()->after('target_key');
            $table->index(
                ['workspace_id', 'agent_instance_id', 'status'],
                'publication_executions_agent_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('publication_executions', function (Blueprint $table): void {
            $table->dropIndex('publication_executions_agent_status_index');
            $table->dropColumn('agent_instance_id');
        });
    }
};
