<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalCatalogCampaignSelection extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'campaign_id',
        'external_catalog_item_id',
        'selection_group_id',
        'available_quantity',
        'warehouse_external_ids',
        'selected_at',
        'status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'available_quantity' => 'integer',
            'warehouse_external_ids' => 'array',
            'selected_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ExternalCatalogCampaign::class, 'campaign_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ExternalCatalogItem::class, 'external_catalog_item_id');
    }
}
