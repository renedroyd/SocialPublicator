<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class WorkspaceDubaiShopIntegration extends Model
{
    use BelongsToWorkspace;

    protected $table = 'workspace_dubai_shop_integrations';

    protected $fillable = [
        'installation_id',
        'api_base_url',
        'api_token',
        'webhook_key_id',
        'webhook_secret',
        'previous_webhook_key_id',
        'previous_webhook_secret',
        'previous_webhook_secret_expires_at',
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
        'previous_webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'previous_webhook_secret' => 'encrypted',
            'previous_webhook_secret_expires_at' => 'datetime',
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
            if (filled($integration->getOriginal('installation_id')) && $integration->isDirty('installation_id')) {
                throw new RuntimeException('A Dubai Shop installation cannot be reassigned to another workspace.');
            }

            $previousKeyConfigured = filled($integration->previous_webhook_key_id)
                || filled($integration->previous_webhook_secret)
                || $integration->previous_webhook_secret_expires_at !== null;

            if ($previousKeyConfigured && (
                blank($integration->previous_webhook_key_id)
                || blank($integration->previous_webhook_secret)
                || $integration->previous_webhook_secret_expires_at === null
            )) {
                throw new RuntimeException('A previous HMAC key requires an ID, secret, and expiration timestamp.');
            }

            if (filled($integration->previous_webhook_key_id)) {
                if (hash_equals((string) $integration->webhook_key_id, (string) $integration->previous_webhook_key_id)) {
                    throw new RuntimeException('The active and previous HMAC key IDs must be different.');
                }

                $keyConflict = self::withoutGlobalScope('workspace')
                    ->where(function ($query) use ($integration): void {
                        $query->where('webhook_key_id', $integration->previous_webhook_key_id)
                            ->orWhere('previous_webhook_key_id', $integration->previous_webhook_key_id);
                    })
                    ->when($integration->exists, fn ($query) => $query->whereKeyNot($integration->getKey()))
                    ->exists();

                if ($keyConflict) {
                    throw new RuntimeException('The previous HMAC key ID is already assigned to another workspace.');
                }
            }

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
