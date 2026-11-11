<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceDubaiShopIntegration extends Model
{
    use BelongsToWorkspace;

    protected $table = 'workspace_dubai_shop_integrations';

    protected $fillable = [
        'api_base_url',
        'api_token',
        'webhook_key_id',
        'webhook_secret',
        'enabled',
        'campaign_dispatch_enabled',
        'timeout',
        'webhook_tolerance_seconds',
        'last_verified_at',
        'last_catalog_synced_at',
        'last_error_at',
        'last_error_message',
    ];

    protected $hidden = [
        'api_token',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'enabled' => 'boolean',
            'campaign_dispatch_enabled' => 'boolean',
            'timeout' => 'integer',
            'webhook_tolerance_seconds' => 'integer',
            'last_verified_at' => 'datetime',
            'last_catalog_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $integration): void {
            $credentialsChanged = $integration->isDirty([
                'api_base_url',
                'api_token',
                'webhook_key_id',
                'webhook_secret',
            ]);

            if (! $integration->enabled || $credentialsChanged) {
                $integration->campaign_dispatch_enabled = false;
                $integration->last_verified_at = null;
                $integration->last_catalog_synced_at = null;
            }
        });
    }

    public function isReady(): bool
    {
        return $this->enabled
            && filled($this->api_base_url)
            && filled($this->api_token)
            && filled($this->webhook_key_id)
            && filled($this->webhook_secret);
    }
}
