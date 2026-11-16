<?php

namespace Tests\Feature;

use App\Jobs\ProcessDubaiShopEvent;
use App\Models\ExternalCatalogItem;
use App\Models\IntegrationEventInbox;
use App\Models\Workspace;
use App\Models\WorkspaceDubaiShopIntegration;
use App\Services\ExternalCatalogSyncService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DubaiShopIntegrationEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_dubai_shop_inventory_event_is_authenticated_persisted_and_applied_as_a_point_in_time_snapshot(): void
    {
        Queue::fake();

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/integration-contracts/dubai-shop-inventory-changed-v1.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $workspace = Workspace::query()->create([
            'name' => 'Dubai Shop E2E',
            'slug' => 'dubai-shop-e2e',
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $installationId = 'a2d0b7e0-01a1-4e33-8a10-40abfa9b2a00';
        $secret = 'cross-app-contract-e2e-secret-with-entropy';

        WorkspaceDubaiShopIntegration::query()->create([
            'installation_id' => $installationId,
            'api_base_url' => 'https://shop.example.test',
            'api_token' => 'catalog-read-token',
            'webhook_key_id' => 'cross-app-e2e-key',
            'webhook_secret' => $secret,
            'enabled' => true,
        ]);

        $previousAvailability = ['warehouses' => [[
            'warehouse_external_id' => '2f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'available_quantity' => 4,
        ]]];

        ExternalCatalogItem::query()->create([
            'source' => ExternalCatalogItem::SOURCE_DUBAI_SHOP,
            'external_id' => $fixture['aggregate_external_id'],
            'product_external_id' => $fixture['aggregate_external_id'],
            'item_type' => 'product',
            'sku' => 'E2E-001',
            'name' => 'Producto E2E',
            'active' => true,
            'visible' => true,
            'price_amount_minor' => 125000,
            'currency' => 'CUP',
            'availability' => $previousAvailability,
            'revision' => 12,
        ]);

        $body = json_encode($fixture, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$installationId.'.'.$body, $secret);

        $this->call('POST', '/api/integrations/dubai-shop/v1/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DUBAISHOP_KEY_ID' => 'cross-app-e2e-key',
            'HTTP_X_DUBAISHOP_SIGNATURE_VERSION' => 'v2',
            'HTTP_X_DUBAISHOP_INSTALLATION_ID' => $installationId,
            'HTTP_X_DUBAISHOP_TIMESTAMP' => $timestamp,
            'HTTP_X_DUBAISHOP_SIGNATURE' => $signature,
        ], $body)->assertAccepted()
            ->assertJsonPath('event_id', $fixture['event_id'])
            ->assertJsonPath('duplicate', false);

        $event = IntegrationEventInbox::query()
            ->where('event_id', $fixture['event_id'])
            ->firstOrFail();

        Queue::assertPushed(ProcessDubaiShopEvent::class);

        Http::fake([
            'https://shop.example.test/api/integrations/social-publicator/v1/items/'.$fixture['aggregate_external_id'] => Http::response([
                'contract_version' => 'v1',
                'data' => [
                    'external_id' => $fixture['aggregate_external_id'],
                    'product_external_id' => $fixture['aggregate_external_id'],
                    'item_type' => 'product',
                    'sku' => 'E2E-001',
                    'name' => 'Producto E2E',
                    'description' => 'Producto de prueba de integración',
                    'category' => null,
                    'brand' => null,
                    'image_url' => null,
                    'active' => true,
                    'visible' => true,
                    'price' => ['amount_minor' => 125000, 'currency' => 'CUP'],
                    'availability' => ['warehouses' => [[
                        'warehouse_external_id' => '2f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
                        'available_quantity' => 99,
                    ]]],
                    'revision' => 99,
                    'updated_at' => now()->toISOString(),
                ],
            ]),
        ]);

        (new ProcessDubaiShopEvent((int) $event->id, (int) $workspace->id))
            ->handle(app(ExternalCatalogSyncService::class));

        $event->refresh();
        $item = ExternalCatalogItem::query()
            ->where('external_id', $fixture['aggregate_external_id'])
            ->firstOrFail();

        $this->assertSame('processed', $event->status);
        $this->assertSame(13, $item->revision);
        $this->assertSame($fixture['data']['availability'], $item->availability);
        $this->assertNotSame(99, $item->availability['warehouses'][0]['available_quantity']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://shop.example.test/api/integrations/social-publicator/v1/items/'.$fixture['aggregate_external_id']
            && $request->hasHeader('Authorization', 'Bearer catalog-read-token'));
    }
}
