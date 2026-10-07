<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_publication_destination', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('publication_id')
                ->constrained('publications')
                ->cascadeOnDelete();

            $table->foreignId('publication_destination_id')
                ->constrained('publication_destinations')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'publication_id',
                'publication_destination_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_publication_destination');
    }
};
