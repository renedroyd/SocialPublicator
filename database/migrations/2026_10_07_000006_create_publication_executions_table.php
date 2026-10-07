<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_executions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('publication_id')
                ->constrained('publications')
                ->cascadeOnDelete();

            $table->string('occurrence_id', 120);

            /*
             * whatsapp
             * facebook
             */
            $table->string('channel', 20);

            /*
             * Stable identifier of the destination.
             *
             * whatsapp: the single WhatsApp destination.
             * facebook:{facebook_group_id}: one Facebook group.
             */
            $table->string('target_key', 160);

            $table->foreignId('facebook_group_id')
                ->nullable()
                ->constrained('facebook_groups')
                ->nullOnDelete();

            /*
             * pending
             * running
             * succeeded
             * failed
             */
            $table->string('status', 20)
                ->default('pending')
                ->index();

            $table->timestamp('started_at')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->string('external_id', 255)->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique('target_key');
            $table->index([
                'publication_id',
                'occurrence_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_executions');
    }
};
