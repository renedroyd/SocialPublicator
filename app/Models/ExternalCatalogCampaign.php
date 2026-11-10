<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExternalCatalogCampaign extends Model
{
    public const SELECTION_MANUAL = 'manual';

    public const SELECTION_AUTOMATIC = 'automatic';

    public const PUBLICATION_GROUPED = 'grouped';

    public const PUBLICATION_INDIVIDUAL = 'individual';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'selection_mode',
        'publication_mode',
        'max_publications_per_day',
        'minimum_interval_minutes',
        'out_of_stock_behavior',
        'restock_behavior',
        'price_change_behavior',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_publications_per_day' => 'integer',
            'minimum_interval_minutes' => 'integer',
        ];
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalCatalogItem::class,
            'external_catalog_campaign_item',
            'campaign_id',
            'external_catalog_item_id',
        )->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalCatalogWarehouse::class,
            'external_catalog_campaign_warehouse',
            'campaign_id',
            'external_catalog_warehouse_id',
        )->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
