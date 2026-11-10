<?php

namespace Tests\Feature;

use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogItem;
use App\Models\ExternalCatalogWarehouse;
use App\Services\ExternalCatalogCampaignSelectionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExternalCatalogCampaignSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function createCampaign(array $overrides = []): array
    {
        $warehouseA = ExternalCatalogWarehouse::query()->create([
            'source' => 'dubai_shop',
            'external_id' => '2f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'code' => 'A',
            'name' => 'Almacén A',
            'active' => true,
            'online_fulfillment_enabled' => true,
        ]);

        $warehouseB = ExternalCatalogWarehouse::query()->create([
            'source' => 'dubai_shop',
            'external_id' => '3f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'code' => 'B',
            'name' => 'Almacén B',
            'active' => true,
            'online_fulfillment_enabled' => true,
        ]);

        $campaign = ExternalCatalogCampaign::query()->create(array_merge([
            'name' => 'Campaña rotativa',
            'is_active' => true,
            'selection_mode' => 'manual',
            'publication_mode' => 'grouped',
            'max_publications_per_day' => 3,
            'minimum_interval_minutes' => 60,
            'out_of_stock_behavior' => 'pause',
            'restock_behavior' => 'publish',
            'price_change_behavior' => 'immediate',
            'rotation_strategy' => 'least_recent',
            'group_size' => 2,
            'posting_weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'posting_window_start' => '09:00:00',
            'posting_window_end' => '21:00:00',
            'timezone' => 'UTC',
        ], $overrides));

        $campaign->warehouses()->attach([$warehouseA->id, $warehouseB->id]);

        return [$campaign, $warehouseA, $warehouseB];
    }

    private function createItem(string $externalId, array $availability, bool $active = true): ExternalCatalogItem
    {
        return ExternalCatalogItem::query()->create([
            'source' => 'dubai_shop',
            'external_id' => $externalId,
            'item_type' => 'product',
            'name' => 'Producto '.$externalId,
            'active' => $active,
            'visible' => true,
            'price_amount_minor' => 1000,
            'currency' => 'CUP',
            'availability' => ['warehouses' => $availability],
            'revision' => 1,
        ]);
    }

    public function test_selection_only_uses_stock_from_campaign_warehouses_and_groups_items(): void
    {
        [$campaign, $warehouseA] = $this->createCampaign();
        $itemA = $this->createItem('9d20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 5],
        ]);
        $itemB = $this->createItem('ad20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 2],
        ]);

        $campaign->items()->attach([$itemA->id, $itemB->id]);

        $selected = app(ExternalCatalogCampaignSelectionEngine::class)
            ->selectNextGroup($campaign, Carbon::parse('2026-11-10 12:00:00', 'UTC'));

        $this->assertCount(2, $selected);
        $this->assertSame(
            $selected->first()->selection_group_id,
            $selected->last()->selection_group_id,
        );
        $this->assertDatabaseHas('external_catalog_campaign_selections', [
            'campaign_id' => $campaign->id,
            'external_catalog_item_id' => $itemA->id,
            'available_quantity' => 5,
            'status' => 'planned',
        ]);
    }

    public function test_campaign_does_not_select_items_with_stock_only_in_unselected_warehouses(): void
    {
        [$campaign, $warehouseA] = $this->createCampaign();
        $item = $this->createItem('bd20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => 'not-selected-warehouse', 'available_quantity' => 10],
        ]);
        $campaign->items()->attach($item->id);
        $campaign->warehouses()->detach();

        $selected = app(ExternalCatalogCampaignSelectionEngine::class)
            ->selectNextGroup($campaign, Carbon::parse('2026-11-10 12:00:00', 'UTC'));

        $this->assertCount(0, $selected);
    }

    public function test_campaign_respects_posting_window_daily_cap_and_minimum_interval(): void
    {
        [$campaign, $warehouseA] = $this->createCampaign([
            'max_publications_per_day' => 1,
            'minimum_interval_minutes' => 60,
        ]);
        $item = $this->createItem('cd20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 3],
        ]);
        $campaign->items()->attach($item->id);

        $engine = app(ExternalCatalogCampaignSelectionEngine::class);
        $first = $engine->selectNextGroup($campaign, Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $second = $engine->selectNextGroup($campaign, Carbon::parse('2026-11-10 12:30:00', 'UTC'));
        $outsideWindow = $engine->selectNextGroup($campaign, Carbon::parse('2026-11-10 22:00:00', 'UTC'));

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertCount(0, $outsideWindow);
    }

    public function test_automatic_mode_selects_all_eligible_catalog_items_without_manual_attachments(): void
    {
        [$campaign, $warehouseA] = $this->createCampaign(['selection_mode' => 'automatic']);
        $item = $this->createItem('dd20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 6],
        ]);

        $selected = app(ExternalCatalogCampaignSelectionEngine::class)
            ->selectNextGroup($campaign, Carbon::parse('2026-11-10 12:00:00', 'UTC'));

        $this->assertCount(1, $selected);
        $this->assertSame($item->id, $selected->first()->external_catalog_item_id);
    }


    public function test_individual_publication_mode_creates_one_item_per_selection_group(): void
    {
        [$campaign, $warehouseA] = $this->createCampaign([
            'publication_mode' => 'individual',
            'group_size' => 5,
        ]);
        $itemA = $this->createItem('ed20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 4],
        ]);
        $itemB = $this->createItem('fd20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 4],
        ]);
        $campaign->items()->attach([$itemA->id, $itemB->id]);

        $selected = app(ExternalCatalogCampaignSelectionEngine::class)
            ->selectNextGroup($campaign, Carbon::parse('2026-11-10 12:00:00', 'UTC'));

        $this->assertCount(1, $selected);
    }

    public function test_round_robin_prioritizes_items_with_fewer_previous_selections(): void
    {
        [$campaign, $warehouseA] = $this->createCampaign([
            'rotation_strategy' => 'round_robin',
            'group_size' => 1,
            'max_publications_per_day' => 3,
        ]);
        $itemA = $this->createItem('0d20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 4],
        ]);
        $itemB = $this->createItem('1d20b7e0-01a1-4e33-8a10-40abfa9b2a00', [
            ['warehouse_external_id' => $warehouseA->external_id, 'available_quantity' => 4],
        ]);
        $campaign->items()->attach([$itemA->id, $itemB->id]);

        $engine = app(ExternalCatalogCampaignSelectionEngine::class);
        $first = $engine->selectNextGroup($campaign, Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $second = $engine->selectNextGroup($campaign, Carbon::parse('2026-11-10 14:00:00', 'UTC'));

        $this->assertSame($itemA->id, $first->first()->external_catalog_item_id);
        $this->assertSame($itemB->id, $second->first()->external_catalog_item_id);
    }

}
