<?php

namespace App\Services;

use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogCampaignTrigger;
use App\Models\ExternalCatalogItem;

class CampaignTriggerService
{
    public function evaluate(
        string $sourceEventId,
        string $eventType,
        ?ExternalCatalogItem $before,
        ExternalCatalogItem $after,
    ): int {
        if (! $before || ! in_array($eventType, ['inventory.changed', 'price.changed'], true)) {
            return 0;
        }

        $created = 0;
        $campaigns = ExternalCatalogCampaign::query()
            ->active()
            ->with(['items', 'warehouses'])
            ->get();

        foreach ($campaigns as $campaign) {
            if (
                $campaign->selection_mode === 'manual'
                && ! $campaign->items->contains('id', $after->id)
            ) {
                continue;
            }

            $warehouses = $campaign->warehouses
                ->filter(fn ($warehouse): bool => $warehouse->active && $warehouse->online_fulfillment_enabled);

            if ($warehouses->isEmpty()) {
                continue;
            }

            $warehouseIds = $warehouses->pluck('external_id')->all();
            $trigger = null;
            $payload = [];

            if ($eventType === 'inventory.changed') {
                $previousQuantity = $this->availableInWarehouses($before, $warehouseIds);
                $currentQuantity = $this->availableInWarehouses($after, $warehouseIds);

                if ($previousQuantity > 0 && $currentQuantity === 0 && $campaign->out_of_stock_behavior === 'publish') {
                    $trigger = 'catalog.item_out_of_stock';
                } elseif ($previousQuantity === 0 && $currentQuantity > 0 && $campaign->restock_behavior === 'publish') {
                    $trigger = 'catalog.item_restocked';
                }

                $payload = [
                    'previous_available_quantity' => $previousQuantity,
                    'current_available_quantity' => $currentQuantity,
                    'warehouse_external_ids' => $warehouseIds,
                ];
            }

            if ($eventType === 'price.changed' && $campaign->price_change_behavior === 'immediate') {
                $previousPrice = [
                    'amount_minor' => $before->price_amount_minor,
                    'currency' => $before->currency,
                ];
                $currentPrice = [
                    'amount_minor' => $after->price_amount_minor,
                    'currency' => $after->currency,
                ];

                if ($previousPrice !== $currentPrice) {
                    $trigger = 'price.changed';
                    $payload = [
                        'previous_price' => $previousPrice,
                        'current_price' => $currentPrice,
                    ];
                }
            }

            if ($trigger === null) {
                continue;
            }

            $result = ExternalCatalogCampaignTrigger::query()->firstOrCreate(
                [
                    'source_event_id' => $sourceEventId,
                    'campaign_id' => $campaign->id,
                    'trigger_type' => $trigger,
                ],
                [
                    'external_catalog_item_id' => $after->id,
                    'payload' => $payload,
                    'status' => 'pending',
                    'triggered_at' => now(),
                ],
            );

            if ($result->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function availableInWarehouses(ExternalCatalogItem $item, array $warehouseIds): int
    {
        return collect($item->availability['warehouses'] ?? [])
            ->filter(fn (array $warehouse): bool => in_array(
                $warehouse['warehouse_external_id'] ?? null,
                $warehouseIds,
                true,
            ))
            ->sum(fn (array $warehouse): int => max(0, (int) ($warehouse['available_quantity'] ?? 0)));
    }
}
