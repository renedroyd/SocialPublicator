<?php

namespace Tests\Feature;

use App\Models\ExternalCatalogItem;
use App\Models\ExternalCatalogWarehouse;
use App\Services\ExternalCatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'external_id' => '9d20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'product_external_id' => '1f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'item_type' => 'variant',
            'sku' => 'EXT-001',
            'name' => 'Producto externo — Variante',
            'description' => 'Descripción pública',
            'category' => ['name' => 'Bebidas', 'slug' => 'bebidas'],
            'brand' => ['name' => 'Clásico', 'slug' => 'clasico'],
            'image_url' => 'https://shop.example.test/storage/products/item.jpg',
            'active' => true,
            'visible' => true,
            'price' => ['amount_minor' => 125000, 'currency' => 'CUP'],
            'availability' => [
                'warehouses' => [[
                    'warehouse_external_id' => '2f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
                    'available_quantity' => 7,
                ]],
            ],
            'revision' => 4,
            'updated_at' => now()->toISOString(),
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.dubai_shop.api_base_url', 'https://shop.example.test');
        config()->set('services.dubai_shop.api_token', 'test-api-token');
    }

    public function test_full_sync_imports_items_and_eligible_warehouses_from_dubai_shop(): void
    {
        $item = $this->itemPayload();

        Http::fake(function ($request) use ($item) {
            if (str_ends_with($request->url(), '/warehouses')) {
                return Http::response([
                    'contract_version' => 'v1',
                    'data' => [[
                        'external_id' => '2f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
                        'code' => 'MAIN',
                        'name' => 'Almacén principal',
                    ]],
                ]);
            }

            if (str_contains($request->url(), '/items')) {
                return Http::response([
                    'contract_version' => 'v1',
                    'data' => [$item],
                    'next_cursor' => null,
                    'has_more' => false,
                ]);
            }

            return Http::response([], 404);
        });

        $result = app(ExternalCatalogSyncService::class)->syncAll();

        $this->assertSame(1, $result['items_synced']);
        $this->assertSame(1, $result['warehouses_synced']);

        $this->assertDatabaseHas('external_catalog_items', [
            'source' => 'dubai_shop',
            'external_id' => $item['external_id'],
            'price_amount_minor' => 125000,
            'currency' => 'CUP',
            'revision' => 4,
            'active' => true,
        ]);

        $this->assertDatabaseHas('external_catalog_warehouses', [
            'external_id' => '2f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'code' => 'MAIN',
            'active' => true,
            'online_fulfillment_enabled' => true,
        ]);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-api-token'));
    }

    public function test_stale_revision_cannot_overwrite_a_newer_external_catalog_item(): void
    {
        $service = app(ExternalCatalogSyncService::class);
        $newer = $this->itemPayload(['revision' => 9, 'name' => 'Nombre vigente']);
        $older = $this->itemPayload(['revision' => 8, 'name' => 'Nombre antiguo']);

        $service->upsertItem($newer);
        $service->upsertItem($older);

        $item = ExternalCatalogItem::query()->where('external_id', $newer['external_id'])->firstOrFail();

        $this->assertSame(9, $item->revision);
        $this->assertSame('Nombre vigente', $item->name);
    }

    public function test_full_reconciliation_marks_items_and_warehouses_missing_from_source_inactive(): void
    {
        $oldItem = ExternalCatalogItem::query()->create([
            'source' => 'dubai_shop',
            'external_id' => '3f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'item_type' => 'product',
            'name' => 'Producto retirado',
            'active' => true,
            'visible' => true,
            'price_amount_minor' => 1000,
            'currency' => 'CUP',
            'revision' => 1,
            'synced_at' => now()->subDay(),
        ]);

        $oldWarehouse = ExternalCatalogWarehouse::query()->create([
            'source' => 'dubai_shop',
            'external_id' => '4f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'code' => 'OLD',
            'name' => 'Almacén retirado',
            'active' => true,
            'online_fulfillment_enabled' => true,
            'synced_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://shop.example.test/api/integrations/social-publicator/v1/warehouses' => Http::response(['data' => []]),
            'https://shop.example.test/api/integrations/social-publicator/v1/items*' => Http::response([
                'data' => [],
                'next_cursor' => null,
                'has_more' => false,
            ]),
        ]);

        app(ExternalCatalogSyncService::class)->syncAll();

        $this->assertFalse($oldItem->fresh()->active);
        $this->assertFalse($oldItem->fresh()->visible);
        $this->assertFalse($oldWarehouse->fresh()->active);
        $this->assertFalse($oldWarehouse->fresh()->online_fulfillment_enabled);
    }
}
