<?php

namespace Tests\Feature;

use App\Models\ExternalCatalogItem;
use App\Models\Product;
use App\Models\Workspace;
use App\Models\WorkspaceDubaiShopIntegration;
use App\Services\DubaiShopApiClient;
use App\Services\DubaiShopIntegrationManager;
use App\Services\ExternalCatalogSyncService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceDubaiShopIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_workspace_uses_its_own_encrypted_catalog_credentials(): void
    {
        $firstWorkspace = Workspace::query()->create(['name' => 'Primero', 'slug' => 'primero']);
        $secondWorkspace = Workspace::query()->create(['name' => 'Segundo', 'slug' => 'segundo']);
        $context = app(WorkspaceContext::class);

        $context->set((int) $firstWorkspace->id);
        $first = WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://first-shop.example.test',
            'api_token' => 'first-private-token',
            'webhook_key_id' => 'first-key',
            'webhook_secret' => 'first-private-secret',
            'enabled' => true,
            'campaign_dispatch_enabled' => true,
        ]);

        $context->set((int) $secondWorkspace->id);
        $second = WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://second-shop.example.test',
            'api_token' => 'second-private-token',
            'webhook_key_id' => 'second-key',
            'webhook_secret' => 'second-private-secret',
            'enabled' => true,
            'campaign_dispatch_enabled' => false,
        ]);

        $context->set((int) $firstWorkspace->id);
        $first->update([
            'last_verified_at' => now(),
            'last_catalog_synced_at' => now(),
            'campaign_dispatch_enabled' => true,
        ]);

        $context->set((int) $secondWorkspace->id);
        $second->update([
            'last_verified_at' => now(),
            'last_catalog_synced_at' => now(),
        ]);

        $this->assertArrayNotHasKey('api_token', $first->toArray());
        $this->assertArrayNotHasKey('webhook_secret', $second->toArray());
        $this->assertNotSame('first-private-token', DB::table('workspace_dubai_shop_integrations')->where('id', $first->id)->value('api_token'));
        $this->assertNotSame('first-private-secret', DB::table('workspace_dubai_shop_integrations')->where('id', $first->id)->value('webhook_secret'));

        Http::fake([
            'https://first-shop.example.test/api/integrations/social-publicator/v1/warehouses' => Http::response(['data' => []]),
            'https://second-shop.example.test/api/integrations/social-publicator/v1/warehouses' => Http::response(['data' => []]),
        ]);

        $context->set((int) $firstWorkspace->id);
        app(DubaiShopApiClient::class)->warehouses();

        $context->set((int) $secondWorkspace->id);
        app(DubaiShopApiClient::class)->warehouses();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'first-shop.example.test')
            && $request->hasHeader('Authorization', 'Bearer first-private-token'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'second-shop.example.test')
            && $request->hasHeader('Authorization', 'Bearer second-private-token'));

        $manager = app(DubaiShopIntegrationManager::class);
        $this->assertTrue($manager->campaignsEnabledForCurrentWorkspace() === false);
        $this->assertSame([(int) $firstWorkspace->id], $manager->configuredWorkspaceIds(campaignsOnly: true));
        $this->assertEqualsCanonicalizing(
            [(int) $firstWorkspace->id, (int) $secondWorkspace->id],
            $manager->configuredWorkspaceIds(),
        );
    }

    public function test_disabled_workspace_integration_does_not_fall_back_to_global_credentials(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Deshabilitado', 'slug' => 'deshabilitado']);
        $context = app(WorkspaceContext::class);
        $context->set((int) $workspace->id);

        WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://disabled-shop.example.test',
            'api_token' => 'disabled-token',
            'enabled' => false,
        ]);

        config([
            'services.dubai_shop.workspace_id' => $workspace->id,
            'services.dubai_shop.api_base_url' => 'https://legacy-shop.example.test',
            'services.dubai_shop.api_token' => 'legacy-token',
        ]);

        $this->assertFalse(app(DubaiShopIntegrationManager::class)->isEnabledForCurrentWorkspace());
        $this->expectException(\RuntimeException::class);
        app(DubaiShopApiClient::class)->warehouses();
    }

    public function test_campaign_dispatch_requires_hmac_verification_full_sync_and_explicit_activation(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Activation gate', 'slug' => 'activation-gate']);
        $context = app(WorkspaceContext::class);
        $context->set((int) $workspace->id);

        $integration = WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://activation-shop.example.test',
            'api_token' => 'activation-token',
            'webhook_key_id' => 'activation-key',
            'webhook_secret' => 'activation-secret',
            'enabled' => true,
            'campaign_dispatch_enabled' => true,
        ]);

        $this->assertTrue($integration->isReady());
        $this->assertFalse($integration->campaign_dispatch_enabled);
        $manager = app(DubaiShopIntegrationManager::class);
        $this->assertFalse($manager->campaignsEnabledForCurrentWorkspace());
        $this->assertSame([], $manager->configuredWorkspaceIds(campaignsOnly: true));

        $integration->update(['last_verified_at' => now()]);
        $this->assertFalse($manager->campaignsEnabledForCurrentWorkspace());
        $this->assertSame([(int) $workspace->id], $manager->configuredWorkspaceIds());

        $integration->update(['last_catalog_synced_at' => now()]);
        $this->assertFalse($manager->campaignsEnabledForCurrentWorkspace());

        $integration->update(['campaign_dispatch_enabled' => true]);
        $this->assertTrue($manager->campaignsEnabledForCurrentWorkspace());
        $this->assertSame([(int) $workspace->id], $manager->configuredWorkspaceIds(campaignsOnly: true));
    }

    public function test_integration_is_not_ready_without_hmac_credentials(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Missing HMAC', 'slug' => 'missing-hmac']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $integration = WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://missing-hmac.example.test',
            'api_token' => 'api-token',
            'enabled' => true,
        ]);

        $this->assertFalse($integration->isReady());
        $this->assertFalse(app(DubaiShopIntegrationManager::class)->isEnabledForCurrentWorkspace());
    }

    public function test_disabling_integration_preserves_external_catalog_data_and_clears_activation(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Preserve catalog', 'slug' => 'preserve-catalog']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $integration = WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://preserve-shop.example.test',
            'api_token' => 'preserve-token',
            'webhook_key_id' => 'preserve-key',
            'webhook_secret' => 'preserve-secret',
            'enabled' => true,
        ]);
        $integration->update([
            'last_verified_at' => now(),
            'last_catalog_synced_at' => now(),
            'campaign_dispatch_enabled' => true,
        ]);

        $item = ExternalCatalogItem::query()->create([
            'source' => ExternalCatalogItem::SOURCE_DUBAI_SHOP,
            'external_id' => 'remote-preserve-1',
            'item_type' => 'product',
            'name' => 'External only item',
            'price_amount_minor' => 10000,
            'currency' => 'CUP',
            'active' => true,
            'visible' => true,
        ]);

        $integration->update(['enabled' => false]);
        $integration->refresh();

        $this->assertDatabaseHas('external_catalog_items', ['id' => $item->id]);
        $this->assertFalse($integration->campaign_dispatch_enabled);
        $this->assertNull($integration->last_verified_at);
        $this->assertNull($integration->last_catalog_synced_at);
    }

    public function test_full_sync_updates_external_catalog_without_creating_local_products(): void
    {
        $workspace = Workspace::query()->create(['name' => 'External catalog', 'slug' => 'external-catalog']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $integration = WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://catalog-shop.example.test',
            'api_token' => 'catalog-token',
            'webhook_key_id' => 'catalog-key',
            'webhook_secret' => 'catalog-secret',
            'enabled' => true,
        ]);
        $integration->update(['last_verified_at' => now()]);

        Http::fake([
            'https://catalog-shop.example.test/api/integrations/social-publicator/v1/warehouses' => Http::response(['data' => []]),
            'https://catalog-shop.example.test/api/integrations/social-publicator/v1/items*' => Http::response([
                'data' => [[
                    'external_id' => 'remote-product-1',
                    'product_external_id' => 'product-1',
                    'item_type' => 'product',
                    'sku' => 'EXT-001',
                    'name' => 'External catalog product',
                    'description' => 'Sourced from Dubai Shop',
                    'category' => ['name' => 'External category', 'slug' => 'external-category'],
                    'brand' => ['name' => 'External brand', 'slug' => 'external-brand'],
                    'price' => ['amount_minor' => 12345, 'currency' => 'CUP'],
                    'availability' => ['warehouses' => []],
                    'active' => true,
                    'visible' => true,
                    'revision' => 1,
                ]],
                'has_more' => false,
                'next_cursor' => null,
            ]),
        ]);

        $result = app(ExternalCatalogSyncService::class)->syncAll();

        $this->assertSame(1, $result['items_synced']);
        $this->assertSame(0, $result['warehouses_synced']);
        $this->assertDatabaseHas('external_catalog_items', [
            'workspace_id' => $workspace->id,
            'external_id' => 'remote-product-1',
        ]);
        $this->assertDatabaseMissing('products', ['nombre' => 'External catalog product']);
        $this->assertNotNull($integration->fresh()->last_catalog_synced_at);
        $this->assertFalse($integration->fresh()->campaign_dispatch_enabled);
    }

    public function test_failed_full_sync_clears_sync_state_and_pauses_campaigns(): void
    {
        $workspace = Workspace::query()->create(['name' => 'Failed sync', 'slug' => 'failed-sync']);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $integration = WorkspaceDubaiShopIntegration::query()->create([
            'api_base_url' => 'https://failed-sync.example.test',
            'api_token' => 'failed-sync-token',
            'webhook_key_id' => 'failed-sync-key',
            'webhook_secret' => 'failed-sync-secret',
            'enabled' => true,
        ]);
        $integration->update([
            'last_verified_at' => now(),
            'last_catalog_synced_at' => now(),
            'campaign_dispatch_enabled' => true,
        ]);

        $manager = app(DubaiShopIntegrationManager::class);
        $this->assertTrue($manager->campaignsEnabledForCurrentWorkspace());

        Http::fake([
            'https://failed-sync.example.test/api/integrations/social-publicator/v1/warehouses' => Http::response(['message' => 'unavailable'], 503),
        ]);

        try {
            app(ExternalCatalogSyncService::class)->syncAll();
            $this->fail('The catalog sync should fail when Dubai Shop returns HTTP 503.');
        } catch (RequestException) {
            // Expected: the service records the failed sync and pauses campaign dispatch.
        }

        $integration->refresh();
        $this->assertNull($integration->last_catalog_synced_at);
        $this->assertFalse($integration->campaign_dispatch_enabled);
        $this->assertFalse($manager->campaignsEnabledForCurrentWorkspace());
    }

    public function test_external_catalog_items_with_same_external_id_are_isolated_by_workspace(): void
    {
        $firstWorkspace = Workspace::query()->create(['name' => 'External first', 'slug' => 'external-first']);
        $secondWorkspace = Workspace::query()->create(['name' => 'External second', 'slug' => 'external-second']);
        $sync = app(ExternalCatalogSyncService::class);
        $payload = [
            'external_id' => 'shared-remote-id',
            'name' => 'First workspace item',
            'active' => true,
            'visible' => true,
            'revision' => 1,
        ];

        app(WorkspaceContext::class)->set((int) $firstWorkspace->id);
        $sync->upsertItem($payload);

        app(WorkspaceContext::class)->set((int) $secondWorkspace->id);
        $sync->upsertItem([...$payload, 'name' => 'Second workspace item']);

        $this->assertDatabaseHas('external_catalog_items', [
            'workspace_id' => $firstWorkspace->id,
            'external_id' => 'shared-remote-id',
            'name' => 'First workspace item',
        ]);
        $this->assertDatabaseHas('external_catalog_items', [
            'workspace_id' => $secondWorkspace->id,
            'external_id' => 'shared-remote-id',
            'name' => 'Second workspace item',
        ]);
    }
}
