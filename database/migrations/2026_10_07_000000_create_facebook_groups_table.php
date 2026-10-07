<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_groups', function (Blueprint $table) {
            $table->id();
            $table->string('facebook_id')->unique();
            $table->string('nombre');
            $table->text('url')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_sincronizacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_groups');
    }
};
