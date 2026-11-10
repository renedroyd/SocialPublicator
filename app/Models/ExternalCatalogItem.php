<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalCatalogItem extends Model
{
    public const SOURCE_DUBAI_SHOP = 'dubai_shop';

    protected $fillable = [
        'source',
        'external_id',
        'product_external_id',
        'item_type',
        'sku',
        'name',
        'description',
        'category_name',
        'category_slug',
        'brand_name',
        'brand_slug',
        'image_url',
        'active',
        'visible',
        'price_amount_minor',
        'currency',
        'availability',
        'revision',
        'source_updated_at',
        'synced_at',
        'sync_run_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'visible' => 'boolean',
            'price_amount_minor' => 'integer',
            'availability' => 'array',
            'revision' => 'integer',
            'source_updated_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
