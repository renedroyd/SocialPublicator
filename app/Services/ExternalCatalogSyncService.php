<?php

namespace App\Services;

use App\Models\ExternalCatalogItem;
use App\Models\ExternalCatalogWarehouse;
use App\Models\IntegrationEventInbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExternalCatalogSyncService
{
    public function __construct(private readonly DubaiShopApiClient $client) {}

    public function syncAll(): array
    {
        $startedAt = now();
        $warehouses = $this->syncWarehouses();
        $itemsSynced = 0;
        $cursor = null;

        do {
            $page = $this->client->items($cursor);

            foreach ($page['data'] ?? [] as $item) {
                $this->upsertItem($item);
                $itemsSynced++;
            }

            $hasMore = (bool) ($page['has_more'] ?? false);
            $nextCursor = $page['next_cursor'] ?? null;

            if ($hasMore && (! is_string($nextCursor) || $nextCursor === '' || $nextCursor === $cursor)) {
                throw new RuntimeException('Dubai Shop returned an invalid catalog pagination cursor.');
            }

            $cursor = $nextCursor;
        } while ($hasMore);

        ExternalCatalogItem::query()
            ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
            ->where(fn ($query) => $query->whereNull('synced_at')->orWhere('synced_at', '<', $startedAt))
            ->update(['active' => false, 'visible' => false, 'updated_at' => now()]);

        return [
            'items_synced' => $itemsSynced,
            'warehouses_synced' => $warehouses,
        ];
    }

    public function syncWarehouses(): int
    {
        $startedAt = now();
        $warehouses = $this->client->warehouses();

        foreach ($warehouses as $warehouse) {
            ExternalCatalogWarehouse::query()->updateOrCreate(
                [
                    'source' => ExternalCatalogItem::SOURCE_DUBAI_SHOP,
                    'external_id' => $warehouse['external_id'],
                ],
                [
                    'code' => $warehouse['code'] ?? null,
                    'name' => $warehouse['name'],
                    'active' => true,
                    'online_fulfillment_enabled' => true,
                    'synced_at' => now(),
                ],
            );
        }

        ExternalCatalogWarehouse::query()
            ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
            ->where(fn ($query) => $query->whereNull('synced_at')->orWhere('synced_at', '<', $startedAt))
            ->update([
                'active' => false,
                'online_fulfillment_enabled' => false,
                'updated_at' => now(),
            ]);

        return count($warehouses);
    }

    public function upsertItem(array $payload): ExternalCatalogItem
    {
        $price = $payload['price'] ?? [];
        $category = $payload['category'] ?? [];
        $brand = $payload['brand'] ?? [];

        $attributes = [
            'product_external_id' => $payload['product_external_id'] ?? null,
            'item_type' => $payload['item_type'] ?? 'product',
            'sku' => $payload['sku'] ?? null,
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'category_name' => $category['name'] ?? null,
            'category_slug' => $category['slug'] ?? null,
            'brand_name' => $brand['name'] ?? null,
            'brand_slug' => $brand['slug'] ?? null,
            'image_url' => $payload['image_url'] ?? null,
            'active' => (bool) ($payload['active'] ?? false),
            'visible' => (bool) ($payload['visible'] ?? false),
            'price_amount_minor' => (int) ($price['amount_minor'] ?? 0),
            'currency' => strtoupper((string) ($price['currency'] ?? '')),
            'availability' => $payload['availability'] ?? ['warehouses' => []],
            'revision' => (int) ($payload['revision'] ?? 0),
            'source_updated_at' => isset($payload['updated_at']) ? Carbon::parse($payload['updated_at']) : null,
            'synced_at' => now(),
        ];

        return DB::transaction(function () use ($payload, $attributes): ExternalCatalogItem {
            $item = ExternalCatalogItem::query()
                ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
                ->where('external_id', $payload['external_id'])
                ->lockForUpdate()
                ->first();

            if ($item && $item->revision > $attributes['revision']) {
                return $item;
            }

            if (! $item) {
                $item = new ExternalCatalogItem([
                    'source' => ExternalCatalogItem::SOURCE_DUBAI_SHOP,
                    'external_id' => $payload['external_id'],
                ]);
            }

            $item->fill($attributes);
            $item->save();

            return $item;
        });
    }

    public function processEvent(IntegrationEventInbox $event): void
    {
        $payload = $event->payload;
        $data = $payload['data'] ?? [];

        if ($event->event_type === 'warehouse.updated') {
            $this->syncWarehouses();

            return;
        }

        $externalId = $data['item_external_id']
            ?? $data['external_id']
            ?? $event->aggregate_external_id;

        if ($event->event_type === 'catalog.item_unpublished') {
            ExternalCatalogItem::query()
                ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
                ->where('external_id', $externalId)
                ->where('revision', '<=', $event->revision)
                ->update([
                    'active' => false,
                    'visible' => false,
                    'revision' => $event->revision,
                    'synced_at' => now(),
                    'updated_at' => now(),
                ]);

            return;
        }

        $item = $this->client->item($externalId);

        if ($item === null) {
            ExternalCatalogItem::query()
                ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
                ->where('external_id', $externalId)
                ->where('revision', '<=', $event->revision)
                ->update([
                    'active' => false,
                    'visible' => false,
                    'revision' => $event->revision,
                    'synced_at' => now(),
                    'updated_at' => now(),
                ]);

            return;
        }

        $this->upsertItem($item);
    }
}
