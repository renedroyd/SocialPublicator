<?php

namespace Tests\Feature;

use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogItem;
use App\Models\ExternalCatalogWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalCatalogCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_persists_selected_external_items_warehouses_and_business_rules(): void
    {
        $item = ExternalCatalogItem::query()->create([
            'source' => ExternalCatalogItem::SOURCE_DUBAI_SHOP,
            'external_id' => '9d20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'item_type' => 'variant',
            'name' => 'Producto externo — Variante',
            'active' => true,
            'visible' => true,
            'price_amount_minor' => 125000,
            'currency' => 'CUP',
            'revision' => 1,
        ]);

        $warehouse = ExternalCatalogWarehouse::query()->create([
            'source' => ExternalCatalogItem::SOURCE_DUBAI_SHOP,
            'external_id' => '2f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'code' => 'MAIN',
            'name' => 'Almacén principal',
            'active' => true,
            'online_fulfillment_enabled' => true,
        ]);

        $campaign = ExternalCatalogCampaign::query()->create([
            'name' => 'Promoción de bebidas',
            'is_active' => true,
            'selection_mode' => ExternalCatalogCampaign::SELECTION_MANUAL,
            'publication_mode' => ExternalCatalogCampaign::PUBLICATION_GROUPED,
            'max_publications_per_day' => 4,
            'minimum_interval_minutes' => 180,
            'out_of_stock_behavior' => 'pause',
            'restock_behavior' => 'publish',
            'price_change_behavior' => 'immediate',
        ]);

        $campaign->items()->attach($item->id, ['sort_order' => 1]);
        $campaign->warehouses()->attach($warehouse->id);

        $this->assertSame($item->id, $campaign->fresh()->items->first()->id);
        $this->assertSame($warehouse->id, $campaign->fresh()->warehouses->first()->id);
        $this->assertSame('grouped', $campaign->fresh()->publication_mode);
        $this->assertSame('publish', $campaign->fresh()->restock_behavior);
        $this->assertSame('immediate', $campaign->fresh()->price_change_behavior);
    }

    public function test_campaign_can_be_paused_without_deleting_its_configuration(): void
    {
        $campaign = ExternalCatalogCampaign::query()->create([
            'name' => 'Campaña temporal',
            'is_active' => true,
            'selection_mode' => 'manual',
            'publication_mode' => 'grouped',
            'max_publications_per_day' => 3,
            'minimum_interval_minutes' => 240,
            'out_of_stock_behavior' => 'pause',
            'restock_behavior' => 'publish',
            'price_change_behavior' => 'immediate',
        ]);

        $campaign->update(['is_active' => false]);

        $this->assertFalse($campaign->fresh()->is_active);
        $this->assertDatabaseHas('external_catalog_campaigns', [
            'id' => $campaign->id,
            'name' => 'Campaña temporal',
            'is_active' => false,
        ]);
    }
}
