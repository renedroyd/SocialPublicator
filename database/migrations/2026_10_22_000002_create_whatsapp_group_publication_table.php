<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_group_publication', function (Blueprint $table) {
            $table->id();

            $table->foreignId('publication_id')
                ->constrained('publications')
                ->cascadeOnDelete();

            $table->foreignId('whatsapp_group_id')
                ->constrained('whatsapp_groups')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'publication_id',
                'whatsapp_group_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_group_publication');
    }
};
