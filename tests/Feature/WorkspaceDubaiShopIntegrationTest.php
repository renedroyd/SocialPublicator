<?php

namespace Tests\Feature;

use App\Models\Workspace;
use App\Models\WorkspaceDubaiShopIntegration;
use App\Services\DubaiShopApiClient;
use App\Services\DubaiShopIntegrationManager;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
