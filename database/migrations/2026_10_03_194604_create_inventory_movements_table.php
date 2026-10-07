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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();

            $table->enum(
                'tipo',
                [
                    'entrada',
                    'salida',
                    'ajuste',
                ]
            );

            $table->decimal(
                'cantidad',
                12,
                2
            );

            $table->decimal(
                'stock_anterior',
                12,
                2
            );

            $table->decimal(
                'stock_nuevo',
                12,
                2
            );

            $table->string('motivo')
                ->nullable();

            $table->string('referencia')
                ->nullable();

            $table->timestamps();

            $table->index([
                'producto_id',
                'created_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
