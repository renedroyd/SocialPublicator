<?php

namespace Tests\Feature;

use App\Models\ExternalCatalogCampaign;
use App\Models\ExternalCatalogCampaignSelection;
use App\Models\ExternalCatalogItem;
use App\Models\ExternalCatalogWarehouse;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use ReflectionMethod;
use App\Models\WhatsAppGroup;
use App\Services\CampaignPublicationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignPublicationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_dispatcher_excludes_unassigned_destinations_when_workspace_uses_social_accounts(): void
    {
        $workspace = Workspace::query()->create([
            'name' => 'Campaign account scope',
            'slug' => 'campaign-account-scope',
        ]);
        app(WorkspaceContext::class)->set((int) $workspace->id);

        $account = SocialAccount::query()->create([
            'platform' => SocialAccount::PLATFORM_WHATSAPP,
            'account_type' => SocialAccount::TYPE_WHATSAPP,
            'display_name' => 'Cuenta de campaña',
        ]);
        $linkedGroup = WhatsAppGroup::query()->create([
            'social_account_id' => $account->id,
            'whatsapp_id' => 'campaign-linked@g.us',
            'name' => 'Grupo vinculado',
            'active' => true,
            'is_admin' => true,
        ]);
        $orphanGroup = WhatsAppGroup::query()->create([
            'whatsapp_id' => 'campaign-orphan@g.us',
            'name' => 'Grupo huérfano',
            'active' => true,
        ]);
        $campaign = ExternalCatalogCampaign::query()->create([
            'name' => 'Campaña multicuenta',
            'is_active' => true,
            'selection_mode' => 'manual',
            'publication_mode' => 'grouped',
            'max_publications_per_day' => 3,
            'minimum_interval_minutes' => 60,
            'out_of_stock_behavior' => 'pause',
            'restock_behavior' => 'publish',
            'price_change_behavior' => 'immediate',
            'destination' => 'whatsapp',
        ]);
        $campaign->whatsappGroups()->attach([$linkedGroup->id, $orphanGroup->id]);

        $dispatcher = app(CampaignPublicationDispatcher::class);
        $method = new ReflectionMethod(CampaignPublicationDispatcher::class, 'authorizedGroups');
        $method->setAccessible(true);
        [$whatsappGroups, $facebookGroups] = $method->invoke($dispatcher, $campaign);

        $this->assertSame([$linkedGroup->id], $whatsappGroups->pluck('id')->all());
        $this->assertCount(0, $facebookGroups);
    }

    public function test_planned_campaign_group_becomes_one_idempotent_bot_publication(): void
    {
        config([
            'services.dubai_shop.api_base_url' => 'https://dubai.example.test',
            'services.dubai_shop.api_token' => 'test-token',
        ]);

        $warehouseId = '2f20b7e0-01a1-4e33-8a10-40abfa9b2a00';
        $itemId = '9d20b7e0-01a1-4e33-8a10-40abfa9b2a00';
        $groupId = (string) Str::uuid();
        $imageUrl = 'https://cdn.example.test/'.str_repeat('catalog-image-', 25).'.webp';

        $warehouse = ExternalCatalogWarehouse::query()->create([
            'source' => ExternalCatalogItem::SOURCE_DUBAI_SHOP,
            'external_id' => $warehouseId,
            'code' => 'MAIN',
            'name' => 'Almacén principal',
            'active' => true,
            'online_fulfillment_enabled' => true,
        ]);

        $item = ExternalCatalogItem::query()->create([
            'source' => ExternalCatalogItem::SOURCE_DUBAI_SHOP,
            'external_id' => $itemId,
            'item_type' => 'product',
            'name' => 'Producto de prueba',
            'active' => true,
            'visible' => true,
            'price_amount_minor' => 250000,
            'currency' => 'CUP',
            'availability' => ['warehouses' => [[
                'warehouse_external_id' => $warehouseId,
                'available_quantity' => 12,
            ]]],
            'revision' => 1,
        ]);

        $campaign = ExternalCatalogCampaign::query()->create([
            'name' => 'Campaña de prueba',
            'is_active' => true,
            'selection_mode' => 'manual',
            'publication_mode' => 'grouped',
            'max_publications_per_day' => 3,
            'minimum_interval_minutes' => 60,
            'out_of_stock_behavior' => 'pause',
            'restock_behavior' => 'publish',
            'price_change_behavior' => 'immediate',
            'destination' => 'whatsapp',
        ]);
        $campaign->warehouses()->attach($warehouse->id);

        $whatsappGroup = WhatsAppGroup::query()->create([
            'whatsapp_id' => '120363000000000000@g.us',
            'name' => 'Grupo de prueba',
            'active' => true,
        ]);
        $campaign->whatsappGroups()->attach($whatsappGroup->id);

        $selection = ExternalCatalogCampaignSelection::query()->create([
            'campaign_id' => $campaign->id,
            'external_catalog_item_id' => $item->id,
            'selection_group_id' => $groupId,
            'available_quantity' => 12,
            'warehouse_external_ids' => [$warehouseId],
            'selected_at' => now(),
            'status' => 'planned',
        ]);

        Http::fake([
            'https://dubai.example.test/*' => Http::response([
                'contract_version' => 'v1',
                'data' => [
                    'external_id' => $itemId,
                    'product_external_id' => $itemId,
                    'item_type' => 'product',
                    'sku' => 'TEST-001',
                    'name' => 'Producto de prueba',
                    'description' => null,
                    'category' => null,
                    'brand' => null,
                    'image_url' => $imageUrl,
                    'active' => true,
                    'visible' => true,
                    'price' => ['amount_minor' => 250000, 'currency' => 'CUP'],
                    'availability' => ['warehouses' => [[
                        'warehouse_external_id' => $warehouseId,
                        'available_quantity' => 12,
                    ]]],
                    'revision' => 2,
                    'updated_at' => now()->toISOString(),
                ],
            ], 200),
        ]);

        $dispatcher = app(CampaignPublicationDispatcher::class);
        $firstRun = $dispatcher->dispatchPending();
        $secondRun = $dispatcher->dispatchPending();

        $publication = Publication::query()->firstOrFail();

        $this->assertSame(1, $firstRun['selections']);
        $this->assertSame(0, $secondRun['selections']);
        $this->assertSame(1, Publication::query()->count());
        $this->assertSame($campaign->id, $publication->external_campaign_id);
        $this->assertSame($groupId, $publication->external_selection_group_id);
        $this->assertSame('whatsapp', $publication->destination);
        $this->assertSame($imageUrl, $publication->image);
        $this->assertSame($whatsappGroup->id, $publication->whatsappGroups()->first()->id);
        $this->assertSame('queued', $selection->fresh()->status);
    }
}
