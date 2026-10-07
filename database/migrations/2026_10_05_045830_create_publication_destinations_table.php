<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_destinations', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 20)->index();
            $table->string('external_id');
            $table->string('name');
            $table->string('url')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique([
                'platform',
                'external_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_destinations');
    }
};
