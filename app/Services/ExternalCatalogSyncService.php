<?php

namespace App\Services;

use App\Models\ExternalCatalogItem;
use App\Models\ExternalCatalogWarehouse;
use App\Models\IntegrationEventInbox;
use App\Models\WorkspaceDubaiShopIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Workspaces\WorkspaceContext;
use RuntimeException;
use Throwable;

class ExternalCatalogSyncService
{
    public function __construct(
        private readonly DubaiShopApiClient $client,
        private readonly CampaignTriggerService $campaignTriggerService,
    ) {}

    public function syncAll(): array
    {
        $workspaceId = app(WorkspaceContext::class)->id();
        $integration = $workspaceId
            ? WorkspaceDubaiShopIntegration::query()->where('workspace_id', $workspaceId)->first()
            : null;

        if ($integration && (! $integration->isReady() || $integration->last_verified_at === null)) {
            throw new RuntimeException('Verify the Dubai Shop connection before synchronizing its catalog.');
        }

        try {
            $syncRunId = (string) Str::uuid();
            $warehouses = $this->syncWarehouses($syncRunId);
            $itemsSynced = 0;
            $cursor = null;

            do {
                $page = $this->client->items($cursor);

                foreach ($page['data'] ?? [] as $item) {
                    $this->upsertItem($item, $syncRunId);
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
                ->where(fn ($query) => $query->whereNull('sync_run_id')->orWhere('sync_run_id', '!=', $syncRunId))
                ->update(['active' => false, 'visible' => false, 'updated_at' => now()]);

            if ($integration) {
                $integration->update([
                    'last_catalog_synced_at' => now(),
                    'last_error_at' => null,
                    'last_error_message' => null,
                ]);
            }

            return [
                'items_synced' => $itemsSynced,
                'warehouses_synced' => $warehouses,
            ];
        } catch (Throwable $exception) {
            if ($integration) {
                $integration->update([
                    'last_error_at' => now(),
                    'last_error_message' => 'No fue posible completar la sincronización del catálogo.',
                    'last_catalog_synced_at' => null,
                    'campaign_dispatch_enabled' => false,
                ]);
            }

            throw $exception;
        }
    }

    public function syncWarehouses(?string $syncRunId = null): int
    {
        $syncRunId ??= (string) Str::uuid();
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
                    'sync_run_id' => $syncRunId,
                ],
            );
        }

        ExternalCatalogWarehouse::query()
            ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
            ->where(fn ($query) => $query->whereNull('sync_run_id')->orWhere('sync_run_id', '!=', $syncRunId))
            ->update([
                'active' => false,
                'online_fulfillment_enabled' => false,
                'updated_at' => now(),
            ]);

        return count($warehouses);
    }

    public function upsertItem(array $payload, ?string $syncRunId = null): ExternalCatalogItem
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
            'sync_run_id' => $syncRunId,
        ];

        return DB::transaction(function () use ($payload, $attributes): ExternalCatalogItem {
            $item = ExternalCatalogItem::query()
                ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
                ->where('external_id', $payload['external_id'])
                ->lockForUpdate()
                ->first();

            if ($item && $item->revision > $attributes['revision']) {
                $item->update([
                    'synced_at' => now(),
                    'sync_run_id' => $attributes['sync_run_id'],
                ]);

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
            $this->markItemUnavailable($externalId, $event->revision);

            return;
        }

        $previousItem = ExternalCatalogItem::query()
            ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
            ->where('external_id', $externalId)
            ->first();

        $item = $this->client->item($externalId);

        if ($item === null) {
            $this->markItemUnavailable($externalId, $event->revision);

            return;
        }

        $syncedItem = $this->upsertItem($item);

        if ($event->revision >= $syncedItem->revision) {
            $this->campaignTriggerService->evaluate(
                $event->event_id,
                $event->event_type,
                $previousItem,
                $syncedItem,
            );
        }
    }

    private function markItemUnavailable(string $externalId, int $revision): void
    {
        ExternalCatalogItem::query()
            ->where('source', ExternalCatalogItem::SOURCE_DUBAI_SHOP)
            ->where('external_id', $externalId)
            ->where('revision', '<=', $revision)
            ->update([
                'active' => false,
                'visible' => false,
                'revision' => $revision,
                'synced_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
