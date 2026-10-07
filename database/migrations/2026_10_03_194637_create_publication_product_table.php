<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publication_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('publicacion_id')
                ->constrained('publicaciones')
                ->cascadeOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            /*
                |--------------------------------------------------------------------------
                | DATOS ESPECÍFICOS DE LA PUBLICACIÓN
                |--------------------------------------------------------------------------
                */

            $table->decimal(
                'cantidad',
                12,
                2
            )->default(1);

            $table->decimal(
                'precio_publicado',
                12,
                2
            )->nullable();

            $table->unsignedInteger(
                'orden'
            )->default(0);

            $table->timestamps();

            $table->unique([
                'publicacion_id',
                'producto_id',
            ]);

            $table->index([
                'publicacion_id',
                'orden',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_product');
    }
};
