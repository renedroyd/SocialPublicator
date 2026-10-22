<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_groups', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_id')->unique();
            $table->string('name');
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_synchronized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_groups');
    }
};
