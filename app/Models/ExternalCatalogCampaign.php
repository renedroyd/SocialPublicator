<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalCatalogCampaign extends Model
{
    use BelongsToWorkspace;

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
        'rotation_strategy',
        'group_size',
        'posting_weekdays',
        'posting_window_start',
        'posting_window_end',
        'timezone',
        'last_selected_at',
        'destination',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_publications_per_day' => 'integer',
            'minimum_interval_minutes' => 'integer',
            'group_size' => 'integer',
            'posting_weekdays' => 'array',
            'last_selected_at' => 'datetime',
        ];
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalCatalogItem::class,
            'external_catalog_campaign_item',
            'campaign_id',
            'external_catalog_item_id',
        )->withPivot(['sort_order', 'last_selected_at', 'selection_count'])->withPivotValue('workspace_id', app(\App\Services\Workspaces\WorkspaceContext::class)->id())->withTimestamps()->orderByPivot('sort_order');
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(
            ExternalCatalogWarehouse::class,
            'external_catalog_campaign_warehouse',
            'campaign_id',
            'external_catalog_warehouse_id',
        )->withPivotValue('workspace_id', app(\App\Services\Workspaces\WorkspaceContext::class)->id())->withTimestamps();
    }

    public function whatsappGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            WhatsAppGroup::class,
            'external_catalog_campaign_whatsapp_group',
            'campaign_id',
            'whatsapp_group_id',
        )->withPivotValue('workspace_id', app(\App\Services\Workspaces\WorkspaceContext::class)->id())->withTimestamps();
    }

    public function facebookGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            FacebookGroup::class,
            'external_catalog_campaign_facebook_group',
            'campaign_id',
            'facebook_group_id',
        )->withPivotValue('workspace_id', app(\App\Services\Workspaces\WorkspaceContext::class)->id())->withTimestamps();
    }

    public function selections(): HasMany
    {
        return $this->hasMany(ExternalCatalogCampaignSelection::class, 'campaign_id');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(ExternalCatalogCampaignTrigger::class, 'campaign_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
