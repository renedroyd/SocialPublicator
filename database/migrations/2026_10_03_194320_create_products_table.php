<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {

            $table->id();

            $table->foreignId('categoria_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('sku')
                ->nullable()
                ->unique();

            $table->string('nombre');

            $table->text('descripcion')
                ->nullable();

            $table->string('unidad')
                ->default('unidad');

            /*
            |--------------------------------------------------------------------------
            | PRECIOS
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'precio_costo',
                12,
                2
            )->default(0);

            $table->decimal(
                'precio_mayorista',
                12,
                2
            )->default(0);

            $table->decimal(
                'precio_minorista',
                12,
                2
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | INVENTARIO
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'stock',
                12,
                2
            )->default(0);

            $table->decimal(
                'stock_minimo',
                12,
                2
            )->default(0);

            $table->decimal(
                'stock_maximo',
                12,
                2
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | IMAGEN
            |--------------------------------------------------------------------------
            */

            $table->string('imagen')
                ->nullable();

            $table->boolean('activo')
                ->default(true);

            $table->timestamps();

            $table->index([
                'activo',
                'stock',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
