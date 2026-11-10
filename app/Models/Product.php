<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'categoria_id',
        'sku',
        'nombre',
        'descripcion',
        'unidad',
        'precio_costo',
        'precio_mayorista',
        'precio_minorista',
        'stock',
        'stock_minimo',
        'stock_maximo',
        'imagen',
        'activo',
    ];

    protected $casts = [
        'precio_costo' => 'decimal:2',
        'precio_mayorista' => 'decimal:2',
        'precio_minorista' => 'decimal:2',

        'stock' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
        'stock_maximo' => 'decimal:2',

        'activo' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | CATEGORÍA
    |--------------------------------------------------------------------------
    */

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /*
    |--------------------------------------------------------------------------
    | MOVIMIENTOS DE INVENTARIO
    |--------------------------------------------------------------------------
    */

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLICACIONES
    |--------------------------------------------------------------------------
    */

    public function publicaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            Publication::class,
            'publication_product'
        )
            ->withPivot([
            'quantity',
            'published_price',
            'sort_order',
            ])
            ->withPivotValue('workspace_id', app(WorkspaceContext::class)->id())
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /*
    |--------------------------------------------------------------------------
    | ESTADO DEL STOCK
    |--------------------------------------------------------------------------
    */

    public function getStockBajoAttribute(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->stock_minimo;
    }

    public function getAgotadoAttribute(): bool
    {
        return $this->stock <= 0;
    }
}