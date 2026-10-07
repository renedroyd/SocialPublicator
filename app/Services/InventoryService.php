<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    /*
    |--------------------------------------------------------------------------
    | ENTRADA
    |--------------------------------------------------------------------------
    */

    public function entrada(
        Product $producto,
        float $cantidad,
        ?string $motivo = null,
        ?string $referencia = null
    ): InventoryMovement {

        return $this->mover(
            $producto,
            'entrada',
            $cantidad,
            $motivo,
            $referencia
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SALIDA
    |--------------------------------------------------------------------------
    */

    public function salida(
        Product $producto,
        float $cantidad,
        ?string $motivo = null,
        ?string $referencia = null
    ): InventoryMovement {

        return $this->mover(
            $producto,
            'salida',
            $cantidad,
            $motivo,
            $referencia
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AJUSTE
    |--------------------------------------------------------------------------
    */

    public function ajuste(
        Product $producto,
        float $nuevoStock,
        ?string $motivo = null,
        ?string $referencia = null
    ): InventoryMovement {

        if ($nuevoStock < 0) {

            throw new InvalidArgumentException(
                'El stock no puede ser negativo.'
            );
        }

        return DB::transaction(
            function () use (
                $producto,
                $nuevoStock,
                $motivo,
                $referencia
            ) {

                $producto = Product::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $producto->id
                    );

                $anterior =
                    (float) $producto->stock;

                $producto->update([
                    'stock' => $nuevoStock,
                ]);

                return InventoryMovement::create([
                    'producto_id' =>
                        $producto->id,

                    'tipo' =>
                        'ajuste',

                    'cantidad' =>
                        abs(
                            $nuevoStock -
                            $anterior
                        ),

                    'stock_anterior' =>
                        $anterior,

                    'stock_nuevo' =>
                        $nuevoStock,

                    'motivo' =>
                        $motivo ??
                        'Ajuste de inventario',

                    'referencia' =>
                        $referencia,
                ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MOVIMIENTO INTERNO
    |--------------------------------------------------------------------------
    */

    protected function mover(
        Product $producto,
        string $tipo,
        float $cantidad,
        ?string $motivo,
        ?string $referencia
    ): InventoryMovement {

        if ($cantidad <= 0) {

            throw new InvalidArgumentException(
                'La cantidad debe ser mayor que cero.'
            );
        }

        return DB::transaction(
            function () use (
                $producto,
                $tipo,
                $cantidad,
                $motivo,
                $referencia
            ) {

                $producto = Product::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $producto->id
                    );

                $anterior =
                    (float) $producto->stock;

                if ($tipo === 'entrada') {

                    $nuevo =
                        $anterior + $cantidad;

                } else {

                    $nuevo =
                        $anterior - $cantidad;
                }

                if ($nuevo < 0) {

                    throw new RuntimeException(
                        "Stock insuficiente para {$producto->nombre}. " .
                        "Disponible: {$anterior}."
                    );
                }

                $producto->update([
                    'stock' => $nuevo,
                ]);

                return InventoryMovement::create([
                    'producto_id' =>
                        $producto->id,

                    'tipo' =>
                        $tipo,

                    'cantidad' =>
                        $cantidad,

                    'stock_anterior' =>
                        $anterior,

                    'stock_nuevo' =>
                        $nuevo,

                    'motivo' =>
                        $motivo ??
                        ucfirst($tipo) .
                        ' de inventario',

                    'referencia' =>
                        $referencia,
                ]);
            }
        );
    }
}