<?php

namespace Tests\Feature;

use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogCampaignTrigger;
use App\Models\ExternalCatalogItem;
use App\Models\ExternalCatalogWarehouse;
use App\Services\CampaignTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTriggerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(string $name, ExternalCatalogItem $item, ExternalCatalogWarehouse $warehouse, array $overrides = []): ExternalCatalogCampaign
    {
        $campaign = ExternalCatalogCampaign::query()->create(array_merge([
            'name' => $name,
            'is_active' => true,
            'selection_mode' => 'manual',
            'publication_mode' => 'grouped',
            'max_publications_per_day' => 3,
            'minimum_interval_minutes' => 240,
            'out_of_stock_behavior' => 'publish',
            'restock_behavior' => 'publish',
            'price_change_behavior' => 'immediate',
        ], $overrides));

        $campaign->items()->attach($item->id);
        $campaign->warehouses()->attach($warehouse->id);

        return $campaign;
    }

    private function item(array $attributes = []): ExternalCatalogItem
    {
        return ExternalCatalogItem::query()->create(array_merge([
            'source' => 'dubai_shop',
            'external_id' => '9d20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'item_type' => 'product',
            'name' => 'Producto de prueba',
            'active' => true,
            'visible' => true,
            'price_amount_minor' => 10000,
            'currency' => 'CUP',
            'availability' => ['warehouses' => []],
            'revision' => 1,
        ], $attributes));
    }

    private function warehouse(string $externalId, string $name): ExternalCatalogWarehouse
    {
        return ExternalCatalogWarehouse::query()->create([
            'source' => 'dubai_shop',
            'external_id' => $externalId,
            'code' => strtoupper(substr($name, -1)),
            'name' => $name,
            'active' => true,
            'online_fulfillment_enabled' => true,
        ]);
    }

    public function test_inventory_events_create_campaign_specific_out_of_stock_and_restock_triggers(): void
    {
        $item = $this->item();
        $warehouseA = $this->warehouse('2f20b7e0-01a1-4e33-8a10-40abfa9b2a00', 'Almacén A');
        $warehouseB = $this->warehouse('3f20b7e0-01a1-4e33-8a10-40abfa9b2a00', 'Almacén B');
        $campaignA = $this->campaign('Campaña A', $item, $warehouseA);
        $campaignB = $this->campaign('Campaña B', $item, $warehouseB);

        $after = new ExternalCatalogItem([
            'source' => 'dubai_shop',
            'external_id' => $item->external_id,
            'item_type' => 'product',
            'name' => $item->name,
            'active' => true,
            'visible' => true,
            'price_amount_minor' => 10000,
            'currency' => 'CUP',
            'availability' => ['warehouses' => [
                ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 0],
                ['warehouse_external_id' => $warehouseB->external_id, 'available_quantity' => 7],
            ]],
            'revision' => 2,
        ]);
        $after->id = $item->id;

        $item->availability = ['warehouses' => [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 5],
            ['warehouse_external_id' => $warehouseB->external_id, 'available_quantity' => 0],
        ]];

        $created = app(CampaignTriggerService::class)->evaluate(
            '4d20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'inventory.changed',
            $item,
            $after,
        );

        $this->assertSame(2, $created);
        $this->assertDatabaseHas('external_catalog_campaign_triggers', [
            'campaign_id' => $campaignA->id,
            'external_catalog_item_id' => $item->id,
            'trigger_type' => 'catalog.item_out_of_stock',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('external_catalog_campaign_triggers', [
            'campaign_id' => $campaignB->id,
            'external_catalog_item_id' => $item->id,
            'trigger_type' => 'catalog.item_restocked',
            'status' => 'pending',
        ]);
    }

    public function test_price_changes_create_triggers_only_for_campaigns_configured_for_immediate_updates(): void
    {
        $item = $this->item();
        $warehouse = $this->warehouse('5f20b7e0-01a1-4e33-8a10-40abfa9b2a00', 'Almacén precio');
        $immediate = $this->campaign('Campaña inmediata', $item, $warehouse);
        $ignored = $this->campaign('Campaña sin avisos', $item, $warehouse, [
            'price_change_behavior' => 'ignore',
        ]);

        $after = new ExternalCatalogItem([
            'source' => 'dubai_shop',
            'external_id' => $item->external_id,
            'item_type' => 'product',
            'name' => $item->name,
            'active' => true,
            'visible' => true,
            'price_amount_minor' => 12500,
            'currency' => 'CUP',
            'availability' => $item->availability,
            'revision' => 2,
        ]);
        $after->id = $item->id;

        $created = app(CampaignTriggerService::class)->evaluate(
            '6f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'price.changed',
            $item,
            $after,
        );

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('external_catalog_campaign_triggers', [
            'campaign_id' => $immediate->id,
            'trigger_type' => 'price.changed',
        ]);
        $this->assertDatabaseMissing('external_catalog_campaign_triggers', [
            'campaign_id' => $ignored->id,
            'trigger_type' => 'price.changed',
        ]);
    }

    public function test_reprocessing_the_same_source_event_does_not_duplicate_campaign_triggers(): void
    {
        $item = $this->item();
        $warehouse = $this->warehouse('7f20b7e0-01a1-4e33-8a10-40abfa9b2a00', 'Almacén idempotente');
        $this->campaign('Campaña idempotente', $item, $warehouse);

        $after = new ExternalCatalogItem([
            'source' => 'dubai_shop',
            'external_id' => $item->external_id,
            'item_type' => 'product',
            'name' => $item->name,
            'active' => true,
            'visible' => true,
            'price_amount_minor' => 11000,
            'currency' => 'CUP',
            'availability' => $item->availability,
            'revision' => 2,
        ]);
        $after->id = $item->id;

        $service = app(CampaignTriggerService::class);
        $first = $service->evaluate('8f20b7e0-01a1-4e33-8a10-40abfa9b2a00', 'price.changed', $item, $after);
        $second = $service->evaluate('8f20b7e0-01a1-4e33-8a10-40abfa9b2a00', 'price.changed', $item, $after);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(1, ExternalCatalogCampaignTrigger::query()->count());
    }
}
