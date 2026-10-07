<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_group_publication', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            $table->foreignId('facebook_grupo_id')->constrained('facebook_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['publicacion_id', 'facebook_grupo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_group_publication');
    }
};
