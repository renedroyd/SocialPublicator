<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalCatalogCampaignTrigger extends Model
{
    protected $fillable = [
        'campaign_id',
        'external_catalog_item_id',
        'source_event_id',
        'trigger_type',
        'payload',
        'status',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'triggered_at' => 'datetime',
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
