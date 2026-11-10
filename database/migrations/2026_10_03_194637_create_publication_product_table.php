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

            $table->foreignId('publication_id')
                ->constrained('publications')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
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
