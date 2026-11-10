<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_event_inbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 80);
            $table->string('contract_version', 10);
            $table->uuid('aggregate_external_id');
            $table->unsignedBigInteger('revision');
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index(['aggregate_external_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_event_inbox');
    }
};
