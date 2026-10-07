<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable();
            return;
        }

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
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(true);
            return;
        }

        Schema::table('publication_executions', function (Blueprint $table): void {
            $table->dropUnique('publication_executions_occurrence_id_target_key_unique');

            $table->unique('target_key');
        });
    }

    private function rebuildSqliteTable(bool $restoreGlobalTargetUniqueness = false): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('publication_executions_new', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('publication_id')
                ->constrained('publications')
                ->cascadeOnDelete();

            $table->string('occurrence_id', 120);
            $table->string('channel', 20);
            $table->string('target_key', 160);

            $table->foreignId('facebook_group_id')
                ->nullable()
                ->constrained('facebook_groups')
                ->nullOnDelete();

            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('external_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index([
                'publication_id',
                'occurrence_id',
            ]);

            if ($restoreGlobalTargetUniqueness) {
                $table->unique('target_key');
            } else {
                $table->unique([
                    'occurrence_id',
                    'target_key',
                ]);
            }
        });

        DB::statement(
            'INSERT INTO publication_executions_new ('.
            'id, publication_id, occurrence_id, channel, target_key, '.
            'facebook_group_id, status, started_at, completed_at, '.
            'external_id, error_message, created_at, updated_at'.
            ') SELECT '.
            'id, publication_id, occurrence_id, channel, target_key, '.
            'facebook_group_id, status, started_at, completed_at, '.
            'external_id, error_message, created_at, updated_at '.
            'FROM publication_executions'
        );

        Schema::drop('publication_executions');
        Schema::rename('publication_executions_new', 'publication_executions');

        Schema::enableForeignKeyConstraints();
    }
};
