<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_statuses', function (Blueprint $table): void {
            $table->string('instance_id', 191)->nullable()->after('name');
        });

        DB::table('bot_statuses')->whereNull('instance_id')->update([
            'instance_id' => DB::raw('name'),
        ]);

        Schema::table('bot_statuses', function (Blueprint $table): void {
            $table->dropUnique('bot_statuses_workspace_name_unique');
            $table->unique(
                ['workspace_id', 'instance_id'],
                'bot_statuses_workspace_instance_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('bot_statuses', function (Blueprint $table): void {
            $table->dropUnique('bot_statuses_workspace_instance_unique');
            $table->unique(['workspace_id', 'name'], 'bot_statuses_workspace_name_unique');
            $table->dropColumn('instance_id');
        });
    }
};
