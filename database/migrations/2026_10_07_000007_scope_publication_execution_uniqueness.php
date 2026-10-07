<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publication_executions', function (Blueprint $table): void {
            $table->dropUnique('publication_executions_target_key_unique');

            $table->unique([
                'occurrence_id',
                'target_key',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('publication_executions', function (Blueprint $table): void {
            $table->dropUnique('publication_executions_occurrence_id_target_key_unique');

            $table->unique('target_key');
        });
    }
};
