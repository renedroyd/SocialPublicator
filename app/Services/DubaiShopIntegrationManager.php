<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\WorkspaceDubaiShopIntegration;
use App\Services\Workspaces\WorkspaceContext;
use RuntimeException;

class DubaiShopIntegrationManager
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function currentSettings(): array
    {
        $workspaceId = $this->context->id();

        if (! $workspaceId) {
            throw new RuntimeException('A workspace context is required for the Dubai Shop integration.');
        }

        $integration = WorkspaceDubaiShopIntegration::query()
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($integration) {
            if (! $integration->isReady()) {
                throw new RuntimeException('Dubai Shop integration is disabled or missing its API credentials for this workspace.');
            }

            $settings = [
                'api_base_url' => $integration->api_base_url,
                'api_token' => $integration->api_token,
                'timeout' => $integration->timeout,
                'campaign_dispatch_enabled' => $integration->campaign_dispatch_enabled,
                'last_verified_at' => $integration->last_verified_at,
                'last_catalog_synced_at' => $integration->last_catalog_synced_at,
                'workspace_id' => $workspaceId,
            ];
        } else {
            $settings = $this->legacySettings($workspaceId);
        }

        if (! $settings) {
            throw new RuntimeException('Configure an enabled Dubai Shop integration for the active workspace.');
        }

        $scheme = strtolower((string) parse_url((string) $settings['api_base_url'], PHP_URL_SCHEME));

        if ($scheme !== 'https' && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dubai Shop API connections must use HTTPS.');
        }

        return $settings;
    }

    public function isEnabledForCurrentWorkspace(): bool
    {
        try {
            $this->currentSettings();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function campaignsEnabledForCurrentWorkspace(): bool
    {
        $workspaceId = $this->context->id();

        if (! $workspaceId) {
            return false;
        }

        $integration = WorkspaceDubaiShopIntegration::query()
            ->where('workspace_id', $workspaceId)
            ->first();

        return $integration !== null
            && $integration->isReady()
            && $integration->campaign_dispatch_enabled
            && $integration->last_verified_at !== null
            && $integration->last_catalog_synced_at !== null;
    }

    /**
     * @return array<int, int>
     */
    public function configuredWorkspaceIds(bool $campaignsOnly = false): array
    {
        $query = WorkspaceDubaiShopIntegration::withoutGlobalScope('workspace')
            ->where('enabled', true)
            ->whereNotNull('api_base_url')
            ->whereNotNull('api_token')
            ->whereNotNull('webhook_key_id')
            ->whereNotNull('webhook_secret')
            ->whereNotNull('last_verified_at');

        if ($campaignsOnly) {
            $query->where('campaign_dispatch_enabled', true)
                ->whereNotNull('last_catalog_synced_at');
        }

        $ids = $query->pluck('workspace_id')->map(fn ($id): int => (int) $id)->all();
        $legacyId = $this->legacyWorkspaceId();

        if ($legacyId && ! in_array($legacyId, $ids, true)) {
            $hasDatabaseRecord = WorkspaceDubaiShopIntegration::withoutGlobalScope('workspace')
                ->where('workspace_id', $legacyId)
                ->exists();

            $legacyReady = filled(config('services.dubai_shop.api_base_url'))
                && filled(config('services.dubai_shop.api_token'))
                && $this->hasLegacyWebhookKeys()
                && ! $campaignsOnly;

            if (! $hasDatabaseRecord && $legacyReady) {
                $ids[] = $legacyId;
            }
        }

        return array_values(array_unique($ids));
    }

    public function legacyWorkspaceId(): ?int
    {
        $configuredId = config('services.dubai_shop.workspace_id');

        if (is_numeric($configuredId) && (int) $configuredId > 0) {
            return (int) $configuredId;
        }

        $workspaceIds = Workspace::query()
            ->withoutGlobalScopes()
            ->limit(2)
            ->pluck('id');

        return $workspaceIds->count() === 1 ? (int) $workspaceIds->first() : null;
    }

    private function hasLegacyWebhookKeys(): bool
    {
        $keys = config('services.dubai_shop.webhook_keys', []);

        if (! is_array($keys) || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $secret = is_array($key) ? ($key['secret'] ?? null) : $key;

            if (is_string($secret) && $secret !== '') {
                return true;
            }
        }

        return false;
    }

    private function legacySettings(int $workspaceId): ?array
    {
        $legacyWorkspaceId = $this->legacyWorkspaceId();

        if ($legacyWorkspaceId !== $workspaceId) {
            return null;
        }

        $baseUrl = (string) config('services.dubai_shop.api_base_url');
        $token = (string) config('services.dubai_shop.api_token');

        if ($baseUrl === '' || $token === '' || ! $this->hasLegacyWebhookKeys()) {
            return null;
        }

        return [
            'api_base_url' => $baseUrl,
            'api_token' => $token,
            'timeout' => (int) config('services.dubai_shop.timeout', 15),
            'campaign_dispatch_enabled' => false,
            'last_verified_at' => null,
            'last_catalog_synced_at' => null,
            'workspace_id' => $workspaceId,
        ];
    }
}
