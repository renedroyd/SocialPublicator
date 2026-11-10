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
                'quantity',
                12,
                2
            )->default(1);

            $table->decimal(
                'published_price',
                12,
                2
            )->nullable();

            $table->unsignedInteger(
                'sort_order'
            )->default(0);

            $table->timestamps();

            $table->unique([
                'publication_id',
                'product_id',
            ]);

            $table->index([
                'publication_id',
                'sort_order',
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
